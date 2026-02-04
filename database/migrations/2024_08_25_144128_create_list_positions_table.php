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
        Schema::create('list_positions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('title');
            $table->string('slug')->unique();
            $table->decimal('rate_per_day', 15, 2);      
            // $table->string('short')->unique();
            $table->boolean('is_regular')->default(0);
            $table->boolean('is_active')->default(1);
            // $table->unsignedInteger('salary_id');
            // $table->foreign('salary_id')->references('id')->on('list_salaries')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('list_positions');
    }
};
