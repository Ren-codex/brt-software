<?php

namespace Tests\Feature\Accounting;

use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\Remittance;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemittancePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        ListStatus::firstOrCreate(['slug' => 'pending'], [
            'name' => 'Pending', 'text_color' => '#fff', 'bg_color' => '#333',
        ]);
    }

    private function administratorWithGrant(?string $level): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Administrator'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($level !== null) {
            $module = Module::where('key', 'sales')->firstOrFail();
            $submodule = $module->submodules()->where('key', 'remittances')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $submodule->id, 'access_level' => $level,
            ]);
        }

        return $user;
    }

    private function makeRemittance(): Remittance
    {
        return Remittance::create([
            'remittance_no' => 'RM-' . uniqid(), 'remittance_date' => now()->toDateString(),
            'summary' => [], 'total_amount' => 1000,
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
            'created_by_id' => User::factory()->create()->id,
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->administratorWithGrant(null);

        $this->actingAs($user)->get('/remittances')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->get('/remittances')->assertOk();
    }

    public function test_store_denied_without_encoder_grant(): void
    {
        $user = $this->administratorWithGrant('view');

        $this->actingAs($user)->post('/remittances', [])->assertForbidden();
    }

    public function test_approve_denied_without_approver_grant(): void
    {
        $remittance = $this->makeRemittance();
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->post("/remittances/{$remittance->id}/approve", [])->assertForbidden();
    }

    public function test_approve_allowed_with_approver_grant(): void
    {
        $remittance = $this->makeRemittance();
        $user = $this->administratorWithGrant('approver');

        $response = $this->actingAs($user)->post("/remittances/{$remittance->id}/approve", []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_remit_denied_without_approver_grant(): void
    {
        $remittance = $this->makeRemittance();
        $user = $this->administratorWithGrant('encoder');

        $this->actingAs($user)->post("/remittances/{$remittance->id}/remit")->assertForbidden();
    }

    public function test_destroy_denied_without_admin_grant(): void
    {
        $remittance = $this->makeRemittance();
        $user = $this->administratorWithGrant('approver');

        $this->actingAs($user)->delete("/remittances/{$remittance->id}")->assertForbidden();
    }
}
