<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeesTableSeeder extends Seeder
{
    /**
     * One employee record per account seeded by UsersTableSeeder.
     *
     * These are not optional. App\Http\Resources\UserResource reads
     * $this->employee->firstname without a null check, so any user without an
     * employee row fatals on every authenticated page load.
     *
     * Keyed on user_id, which is the column the User::employee() hasOne
     * relation joins on.
     *
     * @return void
     */
    public function run()
    {
        $now = now();
        $defaultAvatar = 'employee-pictures/bOln665Q6mTNThtkrx5115CtQzQkAi8X1DdFSRv0.jpg';

        $employees = [
            [
                'user_id' => 1,
                'firstname' => 'Tumacas',
                'lastname' => 'Reniel',
                'middlename' => 'Bentoy',
                'mobile' => '09774246129',
                'sex' => 'Male',
                'position_id' => 1,
                'email' => null,
                'avatar' => $defaultAvatar,
            ],
            [
                'user_id' => 2,
                'firstname' => 'Alden',
                'lastname' => 'Cruz',
                'middlename' => null,
                'mobile' => '09170000002',
                'sex' => 'Male',
                'position_id' => 1,
                'email' => 'admin.demo@example.com',
                'avatar' => 'noavatar.jpg',
            ],
            [
                'user_id' => 3,
                'firstname' => 'Bianca',
                'lastname' => 'Reyes',
                'middlename' => null,
                'mobile' => '09170000003',
                'sex' => 'Female',
                'position_id' => 2,
                'email' => 'salesrep.demo@example.com',
                'avatar' => 'noavatar.jpg',
            ],
            [
                'user_id' => 4,
                'firstname' => 'Carlo',
                'lastname' => 'Mendoza',
                'middlename' => null,
                'mobile' => '09170000004',
                'sex' => 'Male',
                'position_id' => 1,
                'email' => 'hrofficer.demo@example.com',
                'avatar' => 'noavatar.jpg',
            ],
            [
                'user_id' => 5,
                'firstname' => 'Divina',
                'lastname' => 'Santos',
                'middlename' => null,
                'mobile' => '09170000005',
                'sex' => 'Female',
                'position_id' => 3,
                'email' => 'inventory.demo@example.com',
                'avatar' => 'noavatar.jpg',
            ],
            [
                'user_id' => 6,
                'firstname' => 'Elias',
                'lastname' => 'Villanueva',
                'middlename' => null,
                'mobile' => '09170000006',
                'sex' => 'Male',
                'position_id' => 2,
                'email' => 'salesmanager.demo@example.com',
                'avatar' => 'noavatar.jpg',
            ],
            [
                'user_id' => 7,
                'firstname' => 'Fatima',
                'lastname' => 'Delgado',
                'middlename' => null,
                'mobile' => '09170000007',
                'sex' => 'Female',
                'position_id' => 1,
                'email' => 'topmgmt.demo@example.com',
                'avatar' => 'noavatar.jpg',
            ],
            [
                'user_id' => 8,
                'firstname' => 'Super',
                'lastname' => 'Admin',
                'middlename' => null,
                'mobile' => '09170000008',
                'sex' => 'Male',
                'position_id' => 1,
                'email' => 'superadmin@example.com',
                'avatar' => 'noavatar.jpg',
            ],
        ];

        foreach ($employees as $employee) {
            DB::table('employees')->updateOrInsert(
                ['user_id' => $employee['user_id']],
                [
                    'firstname' => $employee['firstname'],
                    'lastname' => $employee['lastname'],
                    'middlename' => $employee['middlename'],
                    'suffix' => null,
                    'mobile' => $employee['mobile'],
                    'birthdate' => '1990-01-01',
                    'avatar' => $employee['avatar'],
                    'sex' => $employee['sex'],
                    'religion' => 'Roman Catholic',
                    'email' => $employee['email'],
                    'position_id' => $employee['position_id'],
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
