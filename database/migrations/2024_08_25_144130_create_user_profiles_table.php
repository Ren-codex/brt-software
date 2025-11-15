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
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('lastname',150)->index();
            $table->text('firstname');
            $table->text('middlename');
            $table->string('suffix')->nullable(); 
            $table->text('mobile');
            $table->text('birthdate');    
            $table->tinyInteger('birthmonth')->index();
            $table->string('avatar', 200)->default('noavatar.jpg');
            $table->string('sex');  
            $table->string('religion');  
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
