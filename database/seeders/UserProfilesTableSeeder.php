<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UserProfilesTableSeeder extends Seeder
{
    /**
     * Auto generated seeder file.
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('user_profiles')->delete();
        
        \DB::table('user_profiles')->insert(array (
            0 => 
            array (
                'id' => 1,
                'lastname' => '',
                'firstname' => '',
                'middlename' => '',
                'mobile' => '09774246129',
                'birthdate' => '',
                'avatar' => 'profile-pictures/bOln665Q6mTNThtkrx5115CtQzQkAi8X1DdFSRv0.jpg',
                'suffix' => NULL,
                'sex' => "Male",
                'religion' => "Roman Catholic",
                'user_id' => 1,
                'created_at' => '2025-10-13 10:11:59',
                'updated_at' => '2025-10-14 16:36:35',
            ),
        ));

        
    }
}