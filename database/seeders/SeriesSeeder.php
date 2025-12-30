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
    }
}