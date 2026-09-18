<?php

namespace Tests\Feature\Sales;

use App\Models\Employee;
use App\Models\ListPosition;
use App\Services\DropdownClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Production names its driver positions per truck and route — "Driver 1 Wingvan",
 * "WV Driver 2", "Driver 2 ST" — so looking for a position titled exactly "Driver"
 * found nothing and the sales order dropdown was empty for all nine drivers.
 */
class DriverDropdownTest extends TestCase
{
    use RefreshDatabase;

    private function employeeWithPosition(string $firstname, string $lastname, string $positionTitle): Employee
    {
        $position = ListPosition::firstOrCreate(
            ['title' => $positionTitle],
            ['slug' => \Str::slug($positionTitle), 'rate_per_day' => 500]
        );

        return Employee::create([
            'firstname' => $firstname,
            'lastname' => $lastname,
            'mobile' => '09170000000',
            'birthdate' => '1990-01-01',
            'sex' => 'Male',
            'religion' => 'None',
            'position_id' => $position->id,
        ]);
    }

    public function test_it_lists_drivers_whose_position_is_named_per_truck(): void
    {
        $ana = $this->employeeWithPosition('Ana', 'Cruz', 'Driver 1 Wingvan');
        $ben = $this->employeeWithPosition('Ben', 'Santos', 'WV Driver 2');
        $carl = $this->employeeWithPosition('Carl', 'Reyes', 'Driver 2 ST');

        $drivers = collect((new DropdownClass)->drivers());

        $this->assertCount(3, $drivers);
        $this->assertEqualsCanonicalizing([$ana->id, $ben->id, $carl->id], $drivers->pluck('value')->all());
        $this->assertContains($ana->fullname.' — Driver 1 Wingvan', $drivers->pluck('name'));
        $this->assertContains($ben->fullname.' — WV Driver 2', $drivers->pluck('name'));
    }

    public function test_it_still_lists_a_plainly_named_driver(): void
    {
        $jane = $this->employeeWithPosition('Jane', 'Dela Cruz', 'Driver');

        $names = collect((new DropdownClass)->drivers())->pluck('name');

        $this->assertCount(1, $names);
        $this->assertContains($jane->fullname.' — Driver', $names);
    }

    public function test_it_excludes_employees_in_non_driver_positions(): void
    {
        $this->employeeWithPosition('Dina', 'Lim', 'Checker 1');
        $this->employeeWithPosition('Eric', 'Tan', 'Laborer 1');

        $this->assertCount(0, (new DropdownClass)->drivers());
    }
}
