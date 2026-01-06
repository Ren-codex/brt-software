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
            'slug' => 'pr_number',
            'prefix' => 'PR',
            'max_digit' => 6,
            'starting_value' => 1
        ));

        Series::create(array(
            'name' => 'Purchase Order',
            'slug' => 'po_number',
            'prefix' => 'PO',
            'max_digit' => 6,
            'starting_value' => 1
        ));
    }
}