<?php

namespace Tests\Feature\Employees;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The name is built as "first middle-initial last", so an employee with no
 * middle name used to come out with a double space between the names —
 * visible anywhere a name is listed, such as the sales order driver dropdown.
 */
class EmployeeFullnameTest extends TestCase
{
    use RefreshDatabase;

    private function employee(array $attributes = []): Employee
    {
        return Employee::create(array_merge([
            'firstname' => 'Ana',
            'lastname' => 'Cruz',
            'mobile' => '09170000000',
            'birthdate' => '1990-01-01',
            'sex' => 'Female',
            'religion' => 'None',
        ], $attributes));
    }

    public function test_a_name_without_a_middle_name_has_single_spaces(): void
    {
        $this->assertSame('Ana Cruz', $this->employee()->fullname);
    }

    public function test_a_middle_name_becomes_an_initial(): void
    {
        $this->assertSame('Ana B. Cruz', $this->employee(['middlename' => 'Bautista'])->fullname);
    }

    public function test_a_suffix_is_appended_after_a_comma(): void
    {
        $employee = $this->employee(['firstname' => 'Juan', 'lastname' => 'Santos', 'suffix' => 'Jr.']);

        $this->assertSame('Juan Santos, Jr.', $employee->fullname);
    }

    public function test_stray_spaces_around_a_stored_name_are_cleaned_up(): void
    {
        $this->assertSame('Ana Cruz', $this->employee(['lastname' => ' Cruz '])->fullname);
    }
}
