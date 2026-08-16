<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            // Free-text delivery location, defaulted from the customer's address
            // in the UI. Named "delivery_location" (not "location") to avoid
            // shadowing the existing location() belongsTo relationship, which
            // Eloquent would resolve to this column instead once it exists.
            // location_id is kept (nullable) for historical records and the
            // existing location filter, but is no longer set on new orders
            // unless the text happens to match a known list_locations name.
            $table->string('delivery_location')->nullable()->after('location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('delivery_location');
        });
    }
};
