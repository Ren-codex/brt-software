<?php

namespace Database\Seeders;

use App\Models\ListStatus;
use Illuminate\Database\Seeder;

/**
 * Adds the status an order sits in once it is delivered and paid but the money
 * is still with the driver.
 *
 * Its own seeder rather than a row in ListStatusesTableSeeder, which inserts a
 * fixed list with explicit ids and cannot be run twice. This one matches on the
 * slug and lets the database choose the id, so it is safe to run on an existing
 * database and safe to run again.
 */
class ForTurnoverStatusSeeder extends Seeder
{
    public function run(): void
    {
        ListStatus::firstOrCreate(
            ['slug' => 'for-turnover'],
            [
                'name' => 'For Turnover',
                'description' => 'Delivered and paid, money not yet handed in',
                // Amber: money the business is owed by its own people, which is
                // a different kind of outstanding from a customer's balance.
                'text_color' => '#ffffff',
                'bg_color' => '#b0702a',
            ]
        );
    }
}
