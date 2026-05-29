<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('replenishment_requests')) {
            Schema::create('replenishment_requests', function (Blueprint $table) {
                $table->id();
                $table->string('reference_no', 50)->unique();
                $table->unsignedInteger('fund_id');
                $table->string('period_label', 150)->nullable();
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->unsignedInteger('expense_count')->default(0);
                $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
                $table->timestamp('submitted_at')->nullable();
                $table->bigInteger('reviewed_by_id')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_notes')->nullable();
                $table->bigInteger('created_by_id')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'fund_id')) {
                $table->unsignedInteger('fund_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('expenses', 'replenishment_request_id')) {
                $table->unsignedBigInteger('replenishment_request_id')->nullable()->after('fund_id');
            }
        });

        if (\DB::connection()->getDriverName() !== 'sqlite') {
            \DB::statement("ALTER TABLE expenses MODIFY COLUMN status ENUM('pending','approved','rejected','released','recorded','submitted','reimbursed') DEFAULT 'recorded'");
        }

        if (!\DB::table('series')->where('slug', 'replenishment_no')->exists()) {
            \DB::table('series')->insert([
                'name'           => 'Replenishment Request',
                'slug'           => 'replenishment_no',
                'prefix'         => 'REP',
                'starting_value' => 1,
                'max_digit'      => 6,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['fund_id', 'replenishment_request_id']);
        });
        if (\DB::connection()->getDriverName() !== 'sqlite') {
            \DB::statement("ALTER TABLE expenses MODIFY COLUMN status ENUM('pending','approved','rejected','released') DEFAULT 'pending'");
        }
        Schema::dropIfExists('replenishment_requests');
        \DB::table('series')->where('slug', 'replenishment_no')->delete();
    }
};
