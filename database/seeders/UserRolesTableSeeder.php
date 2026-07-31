<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserRolesTableSeeder extends Seeder
{
    /**
     * Maps each account from UsersTableSeeder to its role.
     *
     * Roles are looked up by name rather than by id so this stays correct if
     * ListRolesTableSeeder is ever renumbered. is_active must be 1 because
     * HandleInertiaRequests filters the shared `roles` prop on
     * user_roles.is_active, and the sidebar is driven entirely by that prop.
     *
     * Replaces the previous truncate()+insert, which both wiped any roles
     * assigned through the UI and only ever mapped user 1.
     *
     * @return void
     */
    public function run()
    {
        $now = now();

        // Every role currently defined, used for the super admin below.
        $allRoles = DB::table('list_roles')->pluck('name')->all();

        $assignments = [
            1 => ['Administrator'],
            2 => ['Administrator'],
            3 => ['Sales Rep'],
            4 => ['Human Resource Officer'],
            5 => ['Inventory Manager'],
            6 => ['Sales Manager'],
            7 => ['Top Management'],
            // Super admin holds every role, so no menu guard or role: middleware
            // can exclude it even if a future guard omits Administrator.
            8 => $allRoles,
        ];

        foreach ($assignments as $userId => $roleNames) {
            if (! DB::table('users')->where('id', $userId)->exists()) {
                $this->command->warn("Skipping user {$userId}: account does not exist.");
                continue;
            }

            foreach ($roleNames as $roleName) {
                $roleId = DB::table('list_roles')->where('name', $roleName)->value('id');

                if (! $roleId) {
                    $this->command->warn("Skipping user {$userId}: role [{$roleName}] not found in list_roles.");
                    continue;
                }

                DB::table('user_roles')->updateOrInsert(
                    ['user_id' => $userId, 'role_id' => $roleId],
                    [
                        'is_active' => 1,
                        'added_by_id' => 1,
                        'removed_by_id' => null,
                        'removed_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }
}
