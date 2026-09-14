<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who authorised what, and when.
 *
 * The point of a supervisor override is being able to answer "who approved this
 * credit sale?" months later. Recording it here rather than as columns on three
 * different tables keeps one readable trail and leaves those tables alone.
 *
 * No password material is ever written here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervisor_authorizations', function (Blueprint $table) {
            $table->increments('id');
            // The administrator whose credentials were entered.
            $table->unsignedInteger('authorized_by_id');
            // Who was at the keyboard asking for it; null if nobody was logged in.
            $table->unsignedInteger('requested_by_id')->nullable();
            $table->string('action', 60);
            $table->string('subject_type')->nullable();
            $table->unsignedInteger('subject_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->foreign('authorized_by_id')->references('id')->on('users');
            $table->foreign('requested_by_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_authorizations');
    }
};
