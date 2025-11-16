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
        Schema::create('user_roles', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->boolean('is_active')->default(1);
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedTinyInteger('role_id');
            $table->foreign('role_id')->references('id')->on('list_roles')->restrictOnDelete();
            $table->unsignedInteger('added_by_id');
            $table->foreign('added_by_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedInteger('removed_by_id')->nullable();
            $table->foreign('removed_by_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
