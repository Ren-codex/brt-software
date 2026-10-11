<?php

namespace App\Observers;

use App\Events\ApprovalResolved;
use App\Notifications\ApprovalRequestNotification;
use App\Services\Approvals\ApprovalRegistry;
use App\Services\System\Permission\PermissionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Notifies approvers when a record starts waiting on them.
 *
 * One observer serves all seven approval sources: it asks ApprovalRegistry which
 * source a given model belongs to, then reuses that source's own `pending` query
 * to decide whether the record is now waiting. That keeps "what pending means"
 * in exactly one place per module, including the two different ways status is
 * stored.
 *
 * Deliberate choices here:
 *
 *  - It fires on `created` and on `updated`, but on update only when a status
 *    column actually changed. Editing the remarks on a pending purchase order
 *    should not re-notify everyone.
 *
 *  - The work is deferred with afterCommit, so nothing is announced that a
 *    rolled-back transaction then un-does.
 *
 *  - Every failure is swallowed and logged. Broadcasting is synchronous
 *    (ShouldBroadcastNow) because shared hosting has no queue worker, which
 *    means a Pusher outage or a blank key would otherwise throw *inside* the
 *    request that saved the record — turning a failed notification into a failed
 *    approval. The database copy of the notification is written first, so the
 *    bell still shows it on the next poll even when the socket never delivers.
 */
class ApprovalRequestObserver
{
    /**
     * Set false to silence notifications for a block of writes — seeding and
     * bulk imports, where notifying every approver per row is only noise.
     */
    public static bool $enabled = true;

    public function __construct(
        protected ApprovalRegistry $registry,
        protected PermissionService $permissions,
    ) {}

    public function created(Model $model): void
    {
        $this->handle($model, isUpdate: false);
    }

    public function updated(Model $model): void
    {
        $this->handle($model, isUpdate: true);
    }

    protected function handle(Model $model, bool $isUpdate): void
    {
        if (! self::$enabled) {
            return;
        }

        // A sales order is two sources at once — its credit and its delivery
        // are approved separately — so every match is handled, not the first.
        foreach ($this->resolveSources($model) as $key => $source) {
            $this->handleSource($model, $isUpdate, $key, $source);
        }
    }

    protected function handleSource(Model $model, bool $isUpdate, string $key, array $source): void
    {
        // On an edit, only a status change can newly require approval — or,
        // for a source whose pending state is not a status, a change to the
        // columns it names. Such a source ignores status changes altogether:
        // a sales order changes status many times in ways that have nothing
        // to do with its delivery being approved.
        $watch = $source['watch'] ?? ['status', 'status_id'];
        if ($isUpdate && ! $model->wasChanged($watch)) {
            return;
        }

        $id = $model->getKey();

        DB::afterCommit(function () use ($key, $source, $id, $isUpdate) {
            try {
                // The source's own definition of pending, rather than a second
                // guess at it here.
                if (! ($source['pending'])()->whereKey($id)->exists()) {
                    // A status change that leaves the record not pending means
                    // it was just actioned — approved, rejected, voided — from
                    // whichever screen. Retire the requests that announced it.
                    if ($isUpdate) {
                        $this->resolve($key, $source, $id);
                    }

                    return;
                }

                $fresh = ($source['pending'])()->whereKey($id)->first();
                if (! $fresh) {
                    return;
                }

                $row = ($source['row'])($fresh);

                $approvers = $this->permissions->usersWithAccess(
                    $source['module'],
                    $source['submodule'],
                    'approver'
                );

                foreach ($approvers as $approver) {
                    $approver->notify(new ApprovalRequestNotification(
                        sourceKey: $key,
                        sourceLabel: $source['label'],
                        recordId: $id,
                        reference: (string) $row['reference'],
                        summary: $row['summary'] ? (string) $row['summary'] : null,
                        amount: $row['amount'] !== null ? (float) $row['amount'] : null,
                    ));
                }
            } catch (\Throwable $e) {
                // Never let announcing an approval break the save that caused it.
                Log::warning('Approval notification failed', [
                    'source' => $key,
                    'id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * Retire the approval requests for a record that is no longer pending, and
     * tell every approver's open screen to refresh.
     *
     * Without this the bell kept an unread "awaiting approval" row for work
     * somebody had already done, for every other approver, indefinitely.
     */
    protected function resolve(string $key, array $source, int|string $id): void
    {
        $stale = DatabaseNotification::query()
            ->where('type', ApprovalRequestNotification::class)
            ->where('data', 'like', '%"source":"'.$key.'"%')
            // The trailing comma stops id 9 from also matching 90, 91, …
            ->where('data', 'like', '%"record_id":'.json_encode($id).',%')
            ->get();

        foreach ($stale as $notification) {
            $notification->forceFill([
                'data' => array_merge($notification->data, ['resolved' => true]),
                'read_at' => $notification->read_at ?? now(),
            ])->save();
        }

        $userIds = $this->permissions
            ->usersWithAccess($source['module'], $source['submodule'], 'approver')
            ->pluck('id')
            ->merge($stale->pluck('notifiable_id'))
            ->unique()
            ->values()
            ->all();

        if ($userIds) {
            broadcast(new ApprovalResolved($userIds, $key, $id));
        }
    }

    /** @return array<string, array> every source this model belongs to */
    protected function resolveSources(Model $model): array
    {
        return array_filter(
            $this->registry->sources(),
            fn ($source) => $model instanceof $source['model']
        );
    }
}
