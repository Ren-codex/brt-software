<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid', 'released'])
                ->default('pending')
                ->change();
        });

        DB::table('expenses')
            ->where('status', 'paid')
            ->update(['status' => 'released']);

        Schema::table('expenses', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected', 'released'])
                ->default('pending')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid', 'released'])
                ->default('pending')
                ->change();
        });

        DB::table('expenses')
            ->where('status', 'released')
            ->update(['status' => 'paid']);

        Schema::table('expenses', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid'])
                ->default('pending')
                ->change();
        });
    }
};
