<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\ListRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Who may authorise each guarded action.
 *
 * Administrator is assumed wherever nothing is configured, and Super Admin can
 * always authorise, so no setting here can leave an action unperformable.
 */
class AuthorizationSettingsController extends Controller
{
    /** The guarded actions, in the words the people configuring them use. */
    private const ACTIONS = [
        'sales.credit_sale' => [
            'label' => 'Place a credit sale',
            'description' => 'Commits the business to collecting the money later.',
        ],
        'sales.approve_return' => [
            'label' => 'Approve a sales return',
            'description' => 'Puts stock back and moves money out.',
        ],
        'checks.bounce' => [
            'label' => 'Record a bounced check',
            'description' => 'Decides money a customer appeared to pay never arrived, and puts the rep back on the hook.',
        ],
    ];

    public function index()
    {
        $assigned = DB::table('supervisor_action_roles')
            ->get()
            ->groupBy('action')
            ->map(fn ($rows) => $rows->pluck('role_id')->all());

        return inertia('Modules/Libraries/AuthorizationSettings', [
            'actions' => collect(self::ACTIONS)->map(fn ($meta, $key) => [
                'key' => $key,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'role_ids' => $assigned->get($key, []),
            ])->values()->all(),

            'roles' => ListRole::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|string',
            'role_ids' => 'array',
            'role_ids.*' => 'integer|exists:list_roles,id',
        ]);

        if (!array_key_exists($data['action'], self::ACTIONS)) {
            return response()->json(['message' => 'That action is not one of the guarded ones.'], 422);
        }

        DB::transaction(function () use ($data) {
            DB::table('supervisor_action_roles')->where('action', $data['action'])->delete();

            $now = now();
            foreach (array_unique($data['role_ids'] ?? []) as $roleId) {
                DB::table('supervisor_action_roles')->insert([
                    'action' => $data['action'],
                    'role_id' => $roleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        return response()->json([
            'message' => 'Saved.',
            'info' => ($data['role_ids'] ?? []) === []
                ? 'Nobody is listed, so this falls back to Administrator.'
                : 'Those roles can now authorize this action.',
        ]);
    }
}
