<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replenishment_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 50)->unique();
            $table->unsignedInteger('fund_id');
            $table->string('period_label', 150)->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->unsignedInteger('expense_count')->default(0);
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'released'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->bigInteger('reviewed_by_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->bigInteger('created_by_id')->nullable();
            $table->timestamps();
        });

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
        Schema::dropIfExists('replenishment_requests');
        \DB::table('series')->where('slug', 'replenishment_no')->delete();
    }
};
