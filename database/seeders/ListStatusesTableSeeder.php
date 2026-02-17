<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ListStatusesTableSeeder extends Seeder
{
    /**
     * Auto generated seeder file.
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('list_statuses')->delete();
        
        \DB::table('list_statuses')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'Pending',
                'slug' => 'pending',
                'description' => 'Active status',
                'text_color' => '#ffffff',
                'bg_color' => '#28a745',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'Cancelled',
                'slug' => 'cancelled',
                'description' => 'Inactive status',
                'text_color' => '#ffffff',
                'bg_color' => '#6c757d',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'Sales Returned',
                'slug' => 'sales-returned',
                'description' => 'Pending status',
                'text_color' => '#000000',
                'bg_color' => '#ffc107',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),

            3 => 
            array (
                'id' => 4,
                'name' => 'Allowance Applied',
                'slug' => 'allowance-applied',
                'description' => 'Pending status',
                'text_color' => '#000000',
                'bg_color' => '#ffc107',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),

            4 => 
            array (
                'id' => 5,
                'name' => 'Approved',
                'slug' => 'approved',
                'description' => 'Approved status',
                'text_color' => '#000000',
                'bg_color' => '#ffc107',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),

            5 => 
            array (
                'id' => 6,
                'name' => 'Disapproved',
                'slug' => 'disapproved',
                'description' => 'Disapproved status',
                'text_color' => '#000000',
                'bg_color' => '#ffc107',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),

            6 =>    
            array (
                'id' => 7,
                'name' => 'Adjusted',
                'slug' => 'adjusted',
                'description' => 'Adjusted status',
                'text_color' => '#000000',
                'bg_color' => '#ffc107',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),

            7 => 
            array (
                'id' => 8,
                'name' => 'Unpaid',
                'slug' => 'unpaid',
                'description' => 'Unpaid status',
                'text_color' => '#ffffff',
                'bg_color' => '#28a745',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),

            8 => 
            array (
                'id' => 9,
                'name' => 'Paid',
                'slug' => 'paid',
                'description' => 'Paid status',
                'text_color' => '#ffffff',
                'bg_color' => '#000000',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),


            9 =>
            array (
                'id' => 10,
                'name' => 'Closed',
                'slug' => 'closed',
                'description' => 'Closed status',
                'text_color' => '#ffffff',
                'bg_color' => '#000000',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),

            10 =>
            array (
                'id' => 11,
                'name' => 'Partially Paid',
                'slug' => 'partially-paid',
                'description' => 'Partially Paid status',
                'text_color' => '#000000',
                'bg_color' => '#ffc107',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),

            11 => 
            array (
                'id' => 12,
                'name' => 'Open',
                'slug' => 'open',
                'description' => 'Open status',
                'text_color' => '#ffffff',
                'bg_color' => '#000000',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),

            12 => 
            array (
                'id' => 13,
                'name' => 'Liquidated',
                'slug' => 'liquidated',
                'description' => 'Liquidated status',
                'text_color' => '#ffffff',
                'bg_color' => '#000000',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),

            13 =>
            array (
                'id' => 14,
                'name' => 'For Payment',
                'slug' => 'for-payment',
                'description' => 'For Payment status',
                'text_color' => '#000000',
                'bg_color' => '#ffc107',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),

            14 =>
            array (
                'id' => 15,
                'name' => 'Draft',
                'slug' => 'draft',
                'description' => 'Draft status',
                'text_color' => '#000000',
                'bg_color' => '#adadad',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ),
        ));

        
    }
}
