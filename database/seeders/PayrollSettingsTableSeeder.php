<?php

namespace Database\Seeders;

use App\Models\PayrollSetting;
use Illuminate\Database\Seeder;

class PayrollSettingsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'field_name' => 'Incentive Rate',
                'slug' => 'incentive-rate',
                'formula' => '((Product Packaging (kg) * Sold) / 25) * x',
                'value' => 10.00,
                'is_active' => true,
            ],
            [
                'field_name' => 'Overtime Rate',
                'slug' => 'overtime-rate',
                'formula' => '((Daily Rate / Hours Per Day) * x) * OT Hours',
                'value' => 1.25,
                'is_active' => true,
            ],
            [
                'field_name' => 'Hours per Day',
                'slug' => 'hours-per-day',
                'formula' => '',
                'value' => 8,
                'is_active' => true,
            ],
            
        ];

        foreach ($settings as $setting) {
            PayrollSetting::create($setting);
        }
    }
}
