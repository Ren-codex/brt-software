<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Checker position.
 *
 * Checkers on a sales order are now picked from employees by position, like
 * drivers (DropdownClass::checkers()), instead of by the Checker user role.
 * Most checkers have no account to give a role to. Checking is usually a duty
 * on top of a real job, so it is given under "Also works as" on the employee,
 * which leaves their pay where it was.
 *
 * Its rate is 0: nobody is paid as a checker yet. Set the rate under Libraries
 * → Positions before making it anyone's main position.
 *
 * Anyone already picked as a checker through the role keeps appearing: their
 * employee record is given Checker as one of the jobs they also cover.
 */
return new class extends Migration
{
    public function up(): void
    {
        $position = DB::table('list_positions')->whereRaw('LOWER(title) LIKE ?', ['%checker%'])->first();

        $positionId = $position ? $position->id : DB::table('list_positions')->insertGetId([
            'title' => 'Checker',
            'slug' => 'checker',
            'rate_per_day' => 0,
            'is_regular' => 0,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $employeeIds = DB::table('employees')
            ->join('user_roles', 'user_roles.user_id', '=', 'employees.user_id')
            ->join('list_roles', 'list_roles.id', '=', 'user_roles.role_id')
            ->whereRaw('LOWER(list_roles.name) LIKE ?', ['%checker%'])
            ->where('user_roles.is_active', 1)
            ->where('employees.position_id', '!=', $positionId)
            ->distinct()
            ->pluck('employees.id');

        foreach ($employeeIds as $employeeId) {
            DB::table('employee_positions')->insertOrIgnore([
                'employee_id' => $employeeId,
                'position_id' => $positionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $position = DB::table('list_positions')->where('slug', 'checker')->first();

        // Left alone once anyone is paid as a checker: removing a position out
        // from under an employee is not something a rollback should do quietly.
        if ($position && ! DB::table('employees')->where('position_id', $position->id)->exists()) {
            DB::table('list_positions')->where('id', $position->id)->delete();
        }
    }
};
