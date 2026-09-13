<?php

namespace Tests\Feature\Checks;

use App\Models\Check;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_check_starts_pending(): void
    {
        $check = Check::create([
            'direction' => Check::DIRECTION_RECEIVED,
            'check_number' => '0012345',
            'check_date' => now()->addDays(9)->toDateString(),
            'amount' => 100000,
            'source_type' => 'App\Models\Receipt',
            'source_id' => 1,
        ]);

        $this->assertSame(Check::STATUS_PENDING, $check->status);
        $this->assertTrue($check->isPending());
    }

    public function test_scopes_separate_direction_and_status(): void
    {
        $base = [
            'check_number' => '1', 'check_date' => now()->toDateString(), 'amount' => 1,
            'source_type' => 'App\Models\Receipt', 'source_id' => 1,
        ];
        Check::create($base + ['direction' => Check::DIRECTION_RECEIVED]);
        Check::create($base + ['direction' => Check::DIRECTION_ISSUED]);
        Check::create($base + ['direction' => Check::DIRECTION_ISSUED, 'status' => Check::STATUS_CLEARED]);

        $this->assertSame(1, Check::received()->count());
        $this->assertSame(2, Check::issued()->count());
        $this->assertSame(2, Check::pending()->count());
    }
}
