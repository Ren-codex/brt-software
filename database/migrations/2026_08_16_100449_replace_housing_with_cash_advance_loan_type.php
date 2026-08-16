<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite has no ENUM type — Laravel enforces the allowed values via a
            // CHECK constraint generated from Schema::enum(), so change() works
            // the same way it would for any other column type. Widen first so the
            // existing 'housing' rows satisfy the constraint during the data update.
            Schema::table('loans', function (Blueprint $table) {
                $table->enum('loan_type', ['personal', 'salary', 'emergency', 'housing', 'cash_advance'])->change();
            });
            DB::table('loans')->where('loan_type', 'housing')->update(['loan_type' => 'cash_advance']);
            Schema::table('loans', function (Blueprint $table) {
                $table->enum('loan_type', ['personal', 'salary', 'emergency', 'cash_advance'])->change();
            });

            return;
        }

        // Widen the enum first so existing 'housing' rows have somewhere to land,
        // then migrate the data, then narrow the enum to drop 'housing' for good.
        DB::statement("ALTER TABLE loans MODIFY COLUMN loan_type ENUM('personal', 'salary', 'emergency', 'housing', 'cash_advance') NOT NULL");
        DB::table('loans')->where('loan_type', 'housing')->update(['loan_type' => 'cash_advance']);
        DB::statement("ALTER TABLE loans MODIFY COLUMN loan_type ENUM('personal', 'salary', 'emergency', 'cash_advance') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('loans', function (Blueprint $table) {
                $table->enum('loan_type', ['personal', 'salary', 'emergency', 'housing', 'cash_advance'])->change();
            });
            DB::table('loans')->where('loan_type', 'cash_advance')->update(['loan_type' => 'housing']);
            Schema::table('loans', function (Blueprint $table) {
                $table->enum('loan_type', ['personal', 'salary', 'emergency', 'housing'])->change();
            });

            return;
        }

        DB::statement("ALTER TABLE loans MODIFY COLUMN loan_type ENUM('personal', 'salary', 'emergency', 'housing', 'cash_advance') NOT NULL");
        DB::table('loans')->where('loan_type', 'cash_advance')->update(['loan_type' => 'housing']);
        DB::statement("ALTER TABLE loans MODIFY COLUMN loan_type ENUM('personal', 'salary', 'emergency', 'housing') NOT NULL");
    }
};
