<?php

namespace App\Services\Approvals;

use App\Models\Check;
use App\Models\User;
use App\Services\Accounting\GeneralExpenseApprover;
use App\Services\Modules\CheckRegisterClass;
use App\Services\Modules\LoanClass;
use App\Services\Modules\PayrollClass;
use App\Services\Modules\ReplenishmentService;
use App\Services\System\Permission\PermissionService;
use App\Services\System\PurchaseOrder\PurchaseOrderClass;
use App\Services\System\PurchaseOrder\StockReturnClass;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Reads and acts on everything waiting for an approver.
 *
 * Decisions are delegated to the module service that already owns the rule —
 * approving a purchase order here assigns its PO number through SeriesService
 * exactly as the purchase order screen does, and approving a payroll still posts
 * its journal entry. Nothing about *what* an approval means is reimplemented in
 * this class; it only translates one decision into each module's own payload
 * (see the ApprovalRegistry docblock for why those differ).
 *
 * Because the work bypasses the route middleware that normally guards these
 * actions, every method re-checks the caller's `approver` access against the
 * source's own module and submodule. A user sees and acts on exactly the sources
 * they hold that level for, which is why this page is not Administrator-only.
 */
class ApprovalService
{
    /** @var array<int, array> permission maps already resolved this request */
    protected array $mapCache = [];

    public function __construct(
        protected ApprovalRegistry $registry,
        protected PermissionService $permissions,
    ) {}

    /**
     * The source keys this user may approve.
     *
     * Derived from one permission map rather than seven userHasAccess() calls.
     * The bell polls this every 20 seconds per signed-in user, and the per-source
     * version cost about twenty queries each time (a module lookup plus an
     * exists() per source) where the map costs about four.
     */
    public function allowedSourceKeys(?User $user = null): array
    {
        $user ??= Auth::user();
        if (! $user) {
            return [];
        }

        $map = $this->permissionMap($user);

        $allowed = [];
        foreach ($this->registry->sources() as $key => $source) {
            $moduleGrants = $map[$source['module']] ?? null;
            if (! $moduleGrants) {
                continue;
            }

            // A module-wide grant ('_module') covers every submodule on it.
            $levels = array_merge(
                $moduleGrants[$source['submodule']] ?? [],
                $moduleGrants['_module'] ?? [],
            );

            if (array_intersect($levels, ['approver', 'admin'])) {
                $allowed[] = $key;
            }
        }

        return $allowed;
    }

    /**
     * Memoised per instance, so the several calls one request makes — the page
     * and the bell both ask — resolve the map once.
     */
    protected function permissionMap(User $user): array
    {
        if (! isset($this->mapCache[$user->id])) {
            $this->mapCache[$user->id] = $this->permissions->userPermissionMap($user);
        }

        return $this->mapCache[$user->id];
    }

    /**
     * Everything pending that this user may approve, grouped by source.
     *
     * `limit` caps rows per source so one huge backlog cannot crowd out the
     * others — the count is reported separately and stays exact.
     */
    public function pending(?User $user = null, int $limit = 50): array
    {
        $user ??= Auth::user();
        $sources = $this->registry->sources();
        $groups = [];
        $total = 0;

        foreach ($this->allowedSourceKeys($user) as $key) {
            $source = $sources[$key];
            $query = ($source['pending'])();

            $count = (clone $query)->count();
            $total += $count;

            $items = $query->latest()->limit($limit)->get()->map(function ($model) use ($source, $key) {
                $row = ($source['row'])($model);

                return [
                    'id' => $model->getKey(),
                    'source' => $key,
                    'label' => $source['label'],
                    'icon' => $source['icon'],
                    'url' => $source['url'],
                    'can_approve_inline' => $source['can_approve_inline'],
                    'can_reject_inline' => $source['can_reject_inline'],
                    'reference' => $row['reference'],
                    'summary' => $row['summary'],
                    'amount' => $row['amount'],
                    'requested_by' => $row['requested_by'],
                    'requested_at' => $row['requested_at'],
                ];
            })->values();

            $groups[] = [
                'source' => $key,
                'label' => $source['label'],
                'plural' => $source['plural'],
                'icon' => $source['icon'],
                'url' => $source['url'],
                'count' => $count,
                'can_approve_inline' => $source['can_approve_inline'],
                'can_reject_inline' => $source['can_reject_inline'],
                'items' => $items,
            ];
        }

        return [
            'groups' => $groups,
            'total' => $total,
        ];
    }

    /**
     * Past decisions of one kind — 'approved' or 'rejected' — on the sources
     * this user may approve, grouped the same way as pending() so the page can
     * filter and search them alike. Made by anyone, here or on the module's
     * own screen, newest first.
     *
     * `limit` caps rows per source; the count stays exact.
     */
    public function history(string $decision, ?User $user = null, int $limit = 50): array
    {
        $user ??= Auth::user();
        $sources = $this->registry->sources();
        $groups = [];
        $total = 0;

        foreach ($this->allowedSourceKeys($user) as $key) {
            $source = $sources[$key];
            // Null when the source never has this kind of decision (nothing
            // rejects a general expense here, say); it still gets its pill.
            $query = ($source['history'])($decision);

            $count = $query ? (clone $query)->count() : 0;
            $total += $count;

            $records = $query ? $query->limit($limit)->get() : collect();

            $items = $records->map(function ($record) use ($source, $key) {
                $decision = ($source['decision'])($record);
                $model = $decision['model'];
                $row = ($source['history_row'] ?? $source['row'])($model);

                return [
                    'id' => $model->getKey(),
                    // A record decided twice (sent back, then approved) has a
                    // row per decision, so the record id alone is not unique.
                    'key' => $key.'-'.$record->getKey(),
                    'source' => $key,
                    'label' => $source['label'],
                    'icon' => $source['icon'],
                    'url' => $source['url'],
                    'reference' => $row['reference'],
                    'summary' => $row['summary'],
                    'amount' => $row['amount'],
                    'requested_by' => $row['requested_by'],
                    'requested_at' => $row['requested_at'],
                    'decision' => $decision['decision'],
                    'decision_label' => $decision['label'],
                    'decided_by' => $decision['by'],
                    // Some of these columns are not cast to dates; one format
                    // for all of them keeps the browser's parsing reliable.
                    'decided_at' => $decision['at'] ? Carbon::parse($decision['at'])->toIso8601String() : null,
                    'remarks' => $decision['remarks'],
                ];
            })->values();

            $groups[] = [
                'source' => $key,
                'label' => $source['label'],
                'plural' => $source['plural'],
                'icon' => $source['icon'],
                'url' => $source['url'],
                'count' => $count,
                'items' => $items,
            ];
        }

        return [
            'groups' => $groups,
            'total' => $total,
        ];
    }

    /** Just the badge number, for the bell and the menu. */
    public function pendingCount(?User $user = null): int
    {
        $user ??= Auth::user();
        $sources = $this->registry->sources();
        $total = 0;

        foreach ($this->allowedSourceKeys($user) as $key) {
            $total += ($sources[$key]['pending'])()->count();
        }

        return $total;
    }

    /**
     * Apply a decision to one pending record.
     *
     * @param  string  $decision  'approved' or 'disapproved'
     */
    public function decide(string $sourceKey, int $id, string $decision, ?string $remarks = null): array
    {
        $source = $this->registry->get($sourceKey);

        if (! $source) {
            abort(404, 'Unknown approval type.');
        }

        $user = Auth::user();
        if (! $user || ! $this->permissions->userHasAccess($user, $source['module'], $source['submodule'], 'approver')) {
            abort(403, "You do not have permission to approve {$source['plural']}.");
        }

        if (! in_array($decision, ['approved', 'disapproved'], true)) {
            abort(422, 'Decision must be approved or disapproved.');
        }

        $inlineAllowed = $decision === 'approved'
            ? $source['can_approve_inline']
            : $source['can_reject_inline'];

        if (! $inlineAllowed) {
            // Guarded rather than guessed: these need input this page does not
            // collect, and inventing a value would post a wrong journal entry.
            abort(422, "{$source['label']} cannot be {$decision} from here — open {$source['url']} to complete it.");
        }

        // The record must still be pending. Without this, two approvers both
        // looking at the list could each act on the same row.
        $stillPending = ($source['pending'])()->whereKey($id)->exists();
        if (! $stillPending) {
            abort(409, "This {$source['label']} is no longer waiting for approval — it may have been actioned already.");
        }

        return match ($sourceKey) {
            'purchase_order' => $this->decidePurchaseOrder($id, $decision, $remarks),
            'stock_return' => $this->decideStockReturn($id, $decision, $remarks),
            'payroll' => $this->decidePayroll($id, $decision, $remarks),
            'loan' => $this->decideLoan($id, $decision, $remarks),
            'expense' => $this->decideExpense($id),
            'check' => $this->decideCheck($id),
            'delivery' => $this->decideDelivery($id, $decision, $remarks),
            'credit_order' => $this->decideCreditOrder($id, $decision, $remarks),
            'replenishment' => $this->decideReplenishment($id, $remarks),
            default => abort(422, 'This approval type has no inline action.'),
        };
    }

    /** Purchase orders take a `status_id`, and approving also assigns the PO number. */
    protected function decidePurchaseOrder(int $id, string $decision, ?string $remarks): array
    {
        $request = new Request([
            'id' => $id,
            'status_id' => $this->registry->statusId($decision === 'approved' ? 'approved' : 'disapproved'),
            'remarks' => $remarks,
        ]);

        return app(PurchaseOrderClass::class)->updateStatus($request);
    }

    /** Stock returns take 'approved' / 'disapproved' directly. */
    protected function decideStockReturn(int $id, string $decision, ?string $remarks): array
    {
        $request = new Request([
            'status' => $decision,
            'remarks' => $remarks,
        ]);

        return app(StockReturnClass::class)->approve($request, $id);
    }

    /** Payrolls take a status slug; 'approved' lands them on 'for-release'. */
    protected function decidePayroll(int $id, string $decision, ?string $remarks): array
    {
        $request = new Request([
            'status' => $decision,
            'remarks' => $remarks,
        ]);

        return app(PayrollClass::class)->updateStatus($request, $id);
    }

    /**
     * Loans take a status string. LoanClass::updateStatus re-checks 'approver'
     * itself, so releasing a loan can never happen through this path.
     */
    protected function decideLoan(int $id, string $decision, ?string $remarks): array
    {
        $request = new Request([
            'status' => $decision,
            'remarks' => $remarks,
        ]);

        return app(LoanClass::class)->updateStatus($request, $id);
    }

    /**
     * Expenses approve by id alone, but the balance checks still apply — they
     * live in GeneralExpenseApprover precisely so both paths run them.
     */
    protected function decideExpense(int $id): array
    {
        $expense = app(GeneralExpenseApprover::class)->approve($id);

        return [
            'data' => $expense,
            'message' => 'Expense approved. Journal entry posted.',
            'info' => "You've approved the expense and its journal entry is posted.",
        ];
    }

    /**
     * Approving a cheque confirms it cleared. CheckRegisterClass posts the
     * money exactly as the check register's own Confirm button does.
     */
    /** Approving records the delivery and any door collection; rejecting records nothing. */
    protected function decideDelivery(int $id, string $decision, ?string $remarks): array
    {
        $sales = app(\App\Services\Modules\SalesOrderClass::class);

        return $decision === 'approved'
            ? $sales->approveDelivery($id)
            : $sales->rejectDelivery($id, $remarks);
    }

    /** Approving clears the order to go out; rejecting cancels it. */
    protected function decideCreditOrder(int $id, string $decision, ?string $remarks): array
    {
        $sales = app(\App\Services\Modules\SalesOrderClass::class);

        return $decision === 'approved'
            ? $sales->approveCredit($id)
            : $sales->rejectCredit($id, $remarks);
    }

    protected function decideCheck(int $id): array
    {
        $check = app(CheckRegisterClass::class)->markCleared(Check::findOrFail($id));

        return [
            'data' => $check->fresh(),
            'message' => 'Cheque confirmed cleared.',
            'info' => 'The amount has been posted to the bank.',
        ];
    }

    /** Only rejection is inline for replenishments; approving needs a source. */
    protected function decideReplenishment(int $id, ?string $remarks): array
    {
        $result = app(ReplenishmentService::class)->reject($id, $remarks);

        return [
            'data' => $result,
            'message' => 'Replenishment rejected.',
            'info' => "You've rejected the replenishment request.",
        ];
    }
}
