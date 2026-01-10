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
        Schema::create('employees', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('lastname',150)->index();
            $table->text('firstname');
            $table->text('middlename')->nullable();
            $table->string('suffix')->nullable(); 
            $table->text('mobile');
            $table->text('birthdate');    
            $table->string('avatar', 200)->default('noavatar.jpg');
            $table->string('sex');  
            $table->string('religion');  
            $table->string('address')->nullable();  
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedInteger('position_id')->nullable();
            $table->foreign('position_id')->references('id')->on('list_positions')->onDelete('cascade');
            $table->string('email', 255)->nullable()->unique();
            $table->boolean('is_regular')->default(0);
            $table->boolean('is_active')->default(1);
            $table->boolean('is_blacklisted')->default(0);
            $table->unsignedInteger('added_by_id')->nullable();
            $table->foreign('added_by_id')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
