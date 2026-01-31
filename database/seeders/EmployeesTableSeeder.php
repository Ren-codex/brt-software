<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class EmployeesTableSeeder extends Seeder
{
    /**
     * Auto generated seeder file.
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('employees')->delete();
        
        \DB::table('employees')->insert(array (
            0 =>
            array (
                'id' => 1,
                'lastname' => 'Reniel',
                'firstname' => 'Tumacas',
                'middlename' => 'Bentoy',
                'mobile' => '09774246129',
                'birthdate' => '',
                'avatar' => 'employee-pictures/bOln665Q6mTNThtkrx5115CtQzQkAi8X1DdFSRv0.jpg',
                'suffix' => NULL,
                'sex' => "Male",
                'religion' => "Roman Catholic",
                'user_id' => 1,
                'position_id' => 1,
                'created_at' => '2025-10-13 10:11:59',
                'updated_at' => '2025-10-14 16:36:35',
            ),
            1 =>
            array (
                'id' => 2,
                'lastname' => 'Driver',
                'firstname' => 'John',
                'middlename' => 'Doe',
                'mobile' => '09123456789',
                'birthdate' => '1990-01-01',
                'avatar' => 'employee-pictures/bOln665Q6mTNThtkrx5115CtQzQkAi8X1DdFSRv0.jpg',
                'suffix' => NULL,
                'sex' => "Male",
                'religion' => "Roman Catholic",
                'user_id' => 2,
                'position_id' => 2,
                'created_at' => '2025-10-13 10:11:59',
                'updated_at' => '2025-10-14 16:36:35',
            ),
            2 =>
            array (
                'id' => 3,
                'lastname' => 'Sales',
                'firstname' => 'Jane',
                'middlename' => 'Rep',
                'mobile' => '09123456790',
                'birthdate' => '1990-01-01',
                'avatar' => 'employee-pictures/bOln665Q6mTNThtkrx5115CtQzQkAi8X1DdFSRv0.jpg',
                'suffix' => NULL,
                'sex' => "Female",
                'religion' => "Roman Catholic",
                'user_id' => 3,
                'position_id' => 3,
                'created_at' => '2025-10-13 10:11:59',
                'updated_at' => '2025-10-14 16:36:35',
            ),
        ));

        
    }
}