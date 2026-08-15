<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * StockReturnClass::receiveItem() had no status distinct from "fully
     * replaced" for a return item that's only partially received (e.g. 5
     * of 10 units replaced so far) — it reused the 'replaced' status for
     * any partial submission, which then made the stock return's own
     * "all items processed?" check think the item was done, prematurely
     * marking the whole return 'completed' and hiding the Receive Stock
     * button for the remaining quantity. Idempotent, same pattern as the
     * other additive status migrations.
     */
    public function up(): void
    {
        if (!DB::table('list_statuses')->where('slug', 'partial')->exists()) {
            DB::table('list_statuses')->insert([
                'name' => 'Partial',
                'slug' => 'partial',
                'description' => 'Partially resolved status',
                'text_color' => '#000000',
                'bg_color' => '#ffa726',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('list_statuses')->where('slug', 'partial')->delete();
    }
};
