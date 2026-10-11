<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Checker role.
 *
 * Giving a user this role is what makes their employee record selectable as the
 * checker on a sales order (DropdownClass::checkers()). It is a role rather
 * than a position because checking is a duty taken on alongside a real job, and
 * a user can hold several roles where an employee has one position.
 *
 * The role carries no permissions of its own: it marks who can be assigned, and
 * grants nothing in the system until someone gives it access under
 * Libraries → Roles.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('list_roles')->whereRaw('LOWER(name) LIKE ?', ['%checker%'])->exists();

        if (! $exists) {
            DB::table('list_roles')->insert([
                'name' => 'Checker',
                'type' => 'Staff',
                'is_active' => 1,
                'definition' => 'Rides with the driver on deliveries and takes the customer\'s payment at the door. Assign this to make an employee selectable as the checker on a sales order.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // An earlier draft of this feature created a "Checker" *position* with a
        // zero daily rate. Remove it where nobody was ever assigned to it, so it
        // does not sit in the positions list implying it is how checkers are set.
        $position = DB::table('list_positions')->where('slug', 'checker')->where('rate_per_day', 0)->first();

        if ($position && ! DB::table('employees')->where('position_id', $position->id)->exists()) {
            DB::table('list_positions')->where('id', $position->id)->delete();
        }
    }

    public function down(): void
    {
        $role = DB::table('list_roles')->where('name', 'Checker')->first();

        // Left alone once anyone holds it: removing a role out from under its
        // users is not something a rollback should do quietly.
        if ($role && ! DB::table('user_roles')->where('role_id', $role->id)->exists()) {
            DB::table('list_roles')->where('id', $role->id)->delete();
        }
    }
};
