<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A Sales Rep with Encoder ticked on Customers, and Admin not ticked, must be
 * able to edit a customer and flip its Active switch.
 */
class CustomerEncoderEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function salesRepWith(string $level): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Sales Rep'], ['type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);
        RolePermission::firstOrCreate([
            'role_id' => $role->id, 'module_id' => Module::where('key', 'customers')->firstOrFail()->id,
            'submodule_id' => null, 'access_level' => $level,
        ]);

        return $user;
    }

    private function customer(): Customer
    {
        return Customer::create([
            'name' => 'Old Name', 'address' => 'Cebu', 'contact_number' => '09171112222',
            'is_active' => 1, 'is_regular' => 0, 'is_blacklisted' => 0, 'credit_limit' => 50000,
            'added_by_id' => User::factory()->create()->id,
        ]);
    }

    public function test_an_encoder_edits_a_customer_and_the_credit_limit_survives(): void
    {
        $rep = $this->salesRepWith('encoder');
        $customer = $this->customer();

        // The edit form is filled from the list row, with `credit_limit ?? 0`
        // (Customers/Modals/Create.vue), so send what it would send.
        $row = $this->actingAs($rep)->getJson('/customers?option=lists&count=10')->assertOk()->json('data.0');
        $form = [
            'id' => $row['id'], 'name' => 'New Name', 'address' => $row['address'],
            'contact_number' => $row['contact_number'], 'email' => $row['email'],
            'is_regular' => $row['is_regular'], 'is_active' => $row['is_active'],
            'is_blacklisted' => $row['is_blacklisted'], 'credit_limit' => $row['credit_limit'] ?? 0,
        ];

        $this->actingAs($rep)
            ->put("/customers/{$customer->id}", $form)
            ->assertSessionHas('status', true);

        $customer->refresh();
        $this->assertSame('New Name', $customer->name);
        $this->assertEquals(50000, $customer->credit_limit);
    }

    public function test_an_encoder_can_flip_the_active_switch(): void
    {
        $rep = $this->salesRepWith('encoder');
        $customer = $this->customer();

        $this->actingAs($rep)
            ->patchJson("/customers/toggle-active/{$customer->id}", ['is_active' => 0])
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertSame(0, (int) $customer->fresh()->is_active);
    }

    public function test_view_only_cannot_flip_the_active_switch(): void
    {
        $rep = $this->salesRepWith('view');
        $customer = $this->customer();

        $this->actingAs($rep)
            ->patchJson("/customers/toggle-active/{$customer->id}", ['is_active' => 0])
            ->assertForbidden();

        $this->assertSame(1, (int) $customer->fresh()->is_active);
    }
}
