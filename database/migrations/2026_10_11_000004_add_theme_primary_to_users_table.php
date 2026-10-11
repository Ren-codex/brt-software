<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each user's own theme colour.
 *
 * The theme began as one system-wide setting and became a personal one: a
 * colour somebody picks changes their own screen and nobody else's. Null means
 * the default green.
 *
 * The earlier system-wide value in app_settings is removed, since nothing reads
 * it any more and leaving it would suggest it still did something.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'theme_primary')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('theme_primary', 7)->nullable();
            });
        }

        DB::table('app_settings')->where('key', 'theme_primary')->delete();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('theme_primary');
        });
    }
};
