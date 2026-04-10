<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('journal_entry_code', 50)->unique();
            $table->string('journal_number', 50)->unique();
            $table->text('description')->nullable();
            $table->date('entry_date')->nullable();
            $table->string('entry_type', 50)->nullable();
            $table->string('module_type', 100)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('memo')->nullable();
            $table->enum('status', ['pending', 'posted', 'reversal_posted', 'reversed'])->default('pending');
            $table->unsignedInteger('added_by_id')->nullable();
            $table->unsignedInteger('created_by_id')->nullable();
            $table->unsignedInteger('posted_by_id')->nullable();
            $table->foreign('added_by_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('posted_by_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
