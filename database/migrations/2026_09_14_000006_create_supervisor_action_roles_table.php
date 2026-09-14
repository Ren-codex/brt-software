<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which roles may authorise which guarded action.
 *
 * Administrator is the default and is seeded for every action, so behaviour is
 * unchanged until somebody deliberately widens it. An action left with no roles
 * falls back to Administrator rather than becoming impossible, and Super Admin
 * can always authorise — otherwise a bad configuration locks everyone out of an
 * action with no way back in.
 */
return new class extends Migration
{
    private const ACTIONS = ['sales.credit_sale', 'sales.approve_return', 'checks.bounce'];

    public function up(): void
    {
        Schema::create('supervisor_action_roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('action', 60);
            $table->unsignedInteger('role_id');
            $table->timestamps();

            $table->unique(['action', 'role_id']);
            $table->foreign('role_id')->references('id')->on('list_roles')->cascadeOnDelete();
        });

        $administrator = DB::table('list_roles')->where('name', 'Administrator')->first();

        if (!$administrator) {
            return; // Nothing to seed against; the Administrator fallback still applies.
        }

        $now = now();
        DB::table('supervisor_action_roles')->insert(array_map(
            fn ($action) => ['action' => $action, 'role_id' => $administrator->id, 'created_at' => $now, 'updated_at' => $now],
            self::ACTIONS
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_action_roles');
    }
};
