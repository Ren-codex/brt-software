<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Not every customer has a contact number on file — a store taken over from
 * the old records often doesn't — and saving one without it failed outright.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('contact_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('customers')->whereNull('contact_number')->update(['contact_number' => '']);

        Schema::table('customers', function (Blueprint $table) {
            $table->string('contact_number')->nullable(false)->change();
        });
    }
};
