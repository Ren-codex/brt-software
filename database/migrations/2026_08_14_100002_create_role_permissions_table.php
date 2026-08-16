<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            // list_roles.id is a legacy tinyIncrements() (tinyint unsigned), not the
            // standard bigint foreignId() assumes -- match user_roles' established
            // pattern for referencing it.
            $table->unsignedTinyInteger('role_id');
            $table->foreign('role_id')->references('id')->on('list_roles')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            // Nullable = grant applies to the whole module (all its submodules).
            // Note: a DB-level unique constraint across a nullable column would not
            // reliably prevent duplicate module-wide rows (MySQL treats each NULL as
            // distinct), so the save endpoint (Task 6) enforces uniqueness by
            // deleting-and-recreating a role's grants atomically rather than relying
            // on a DB constraint for that case.
            $table->foreignId('submodule_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('access_level'); // encoder | approver | releaser | void | view | admin
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
