<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    /**
     * One sign-in-ready account per role in list_roles, plus the owner account.
     *
     * User id 1 is hardcoded as added_by_id / created_by_id in
     * CustomersTableSeeder and PurchaseOrdersTableSeeder, so rbt0213 must stay
     * at id 1.
     *
     * Two companion seeders complete these accounts:
     *   UserRolesTableSeeder  - assigns the role (runs next, truncates first)
     *   EmployeesTableSeeder  - the employee row each account needs, because
     *                           App\Http\Resources\UserResource dereferences
     *                           $this->employee->firstname with no null check
     *
     * Uses updateOrInsert so the seeder can be re-run without deleting users
     * that other tables already reference.
     *
     * @return void
     */
    public function run()
    {
        $now = now();

        // Password shared by every demo account below.
        $demoPassword = 'password';

        $users = [
            [
                'id' => 1,
                'username' => 'rbt0213',
                'email' => 'ren.zeon0213@gmail.com',
                'password' => 'b0uy4nt-4dm1n',
            ],
            [
                'id' => 2,
                'username' => 'admin.demo',
                'email' => 'admin.demo@example.com',
                'password' => $demoPassword,
            ],
            [
                'id' => 3,
                'username' => 'salesrep.demo',
                'email' => 'salesrep.demo@example.com',
                'password' => $demoPassword,
            ],
            [
                'id' => 4,
                'username' => 'hrofficer.demo',
                'email' => 'hrofficer.demo@example.com',
                'password' => $demoPassword,
            ],
            [
                'id' => 5,
                'username' => 'inventory.demo',
                'email' => 'inventory.demo@example.com',
                'password' => $demoPassword,
            ],
            [
                'id' => 6,
                'username' => 'salesmanager.demo',
                'email' => 'salesmanager.demo@example.com',
                'password' => $demoPassword,
            ],
            [
                'id' => 7,
                'username' => 'topmgmt.demo',
                'email' => 'topmgmt.demo@example.com',
                'password' => $demoPassword,
            ],
            // Super admin: UserRolesTableSeeder grants this account every role
            // in list_roles, so every menu item and guarded route is reachable.
            [
                'id' => 8,
                'username' => 'superadmin',
                'email' => 'superadmin@example.com',
                'password' => $demoPassword,
            ],
        ];

        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['id' => $user['id']],
                [
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'password' => Hash::make($user['password']),
                    // is_active and email_verified_at must be set, or the
                    // is_active and verified middleware reject the login.
                    'is_active' => 1,
                    'is_new' => 0,
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                    'remember_token' => null,
                    'email_verified_at' => $now,
                    'password_changed_at' => $now,
                    'two_factor_confirmed_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
