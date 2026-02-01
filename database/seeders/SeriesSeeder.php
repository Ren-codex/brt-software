<?php

namespace Database\Seeders;

use App\Models\Series;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use DB;

class SeriesSeeder extends Seeder
{
    public function run()
    {
        Series::create(array(
            'name' => 'Remittance',
            'slug' => 'remittance',
            'prefix' => 'REM',
            'max_digit' => 6,
            'starting_value' => 1
        ));

        Series::create(array(
            'name' => 'Batch Code',
            'slug' => 'batch_code',
            'prefix' => 'BATCH',
            'max_digit' => 6,
            'starting_value' => 1
        ));

        Series::create(array(
            'name' => 'Purchase Request',
            'slug' => 'purchase_request',
            'prefix' => 'PR',
            'max_digit' => 6,
            'starting_value' => 1
        ));

        Series::create(array(
            'name' => 'Purchase Order',
            'slug' => 'purchase_order',
            'prefix' => 'PO',
            'max_digit' => 6,
            'starting_value' => 1
        ));

        Series::create(array(
            'name' => 'Received Number',
            'slug' => 'received_no',
            'prefix' => 'REC',
            'max_digit' => 6,
            'starting_value' => 1
        ));

        Series::create(array(
            'name' => 'Payroll Number',
            'slug' => 'payroll_number',
            'prefix' => 'PAY',
            'max_digit' => 6,
            'starting_value' => 1
        ));
    }
}