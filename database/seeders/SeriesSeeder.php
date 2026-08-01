<?php

namespace Database\Seeders;

use App\Models\Series;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeriesSeeder extends Seeder
{
    public function run()
    {
        $timestamp = now();

        $seriesRows = [
            [
                'name' => 'Remittance',
                'slug' => 'remittance',
                'prefix' => 'REM',
                'max_digit' => 6,
                'starting_value' => 1,
            ],
            [
                'name' => 'Batch Code',
                'slug' => 'batch_code',
                'prefix' => 'B',
                'max_digit' => 6,
                'starting_value' => 1,
            ],
            [
                'name' => 'Purchase Request',
                'slug' => 'purchase_request',
                'prefix' => 'PR',
                'max_digit' => 6,
                'starting_value' => 1,
            ],
            [
                'name' => 'Purchase Order',
                'slug' => 'purchase_order',
                'prefix' => 'PO',
                'max_digit' => 6,
                'starting_value' => 1,
            ],
            [
                'name' => 'Received Number',
                'slug' => 'received_no',
                'prefix' => 'REC',
                'max_digit' => 6,
                'starting_value' => 1,
            ],
            [
                'name' => 'Payroll Number',
                'slug' => 'payroll_number',
                'prefix' => 'PAY',
                'max_digit' => 6,
                'starting_value' => 1,
            ],
            [
                'name' => 'Loan Number',
                'slug' => 'loan_number',
                'prefix' => 'LN',
                'max_digit' => 6,
                'starting_value' => 1,
            ],
            [
                'name' => 'Stock Return',
                'slug' => 'stock_return',
                'prefix' => 'SR',
                'max_digit' => 6,
                'starting_value' => 1,
            ],
        ];

        $rows = array_map(function ($row) use ($timestamp) {
            return array_merge($row, [
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }, $seriesRows);

        DB::table('series')->upsert(
            $rows,
            ['slug'],
            ['name', 'prefix', 'max_digit', 'starting_value', 'updated_at']
        );
    }
}
