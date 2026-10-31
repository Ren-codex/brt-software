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
 * Editing a customer from the Update Customer window saves what was typed.
 */
class CustomerUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function encoder(): User
    {
        $role = ListRole::create(['name' => 'Encoder '.uniqid(), 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'customers')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => null, 'access_level' => 'encoder',
        ]);

        return $user;
    }

    /** Exactly what the Update Customer window sends. */
    private function formFor(Customer $customer, array $changes = []): array
    {
        return array_merge([
            'id' => $customer->id,
            'name' => $customer->name,
            'address' => $customer->address,
            'contact_number' => $customer->contact_number,
            'email' => $customer->email,
            'is_regular' => $customer->is_regular,
            'is_active' => $customer->is_active,
            'is_blacklisted' => $customer->is_blacklisted,
            'credit_limit' => $customer->credit_limit ?? 0,
            'option' => 'lists',
        ], $changes);
    }

    public function test_an_edit_is_saved(): void
    {
        $user = $this->encoder();
        $customer = Customer::create([
            'name' => 'Old Name', 'address' => 'Old Address', 'contact_number' => '09000000000',
            'is_active' => 1, 'is_regular' => 0, 'is_blacklisted' => 0, 'credit_limit' => 0,
            'added_by_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->from('/customers')
            ->put("/customers/{$customer->id}", $this->formFor($customer, [
                'name' => 'New Name',
                'address' => 'New Address',
                'credit_limit' => 5000,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect('/customers');

        $customer->refresh();
        $this->assertSame('New Name', $customer->name);
        $this->assertSame('New Address', $customer->address);
        $this->assertEquals(5000, (float) $customer->credit_limit);
    }
    /**
     * The window is filled from the list row, then sent back as it is. Whatever
     * the row does not carry comes back empty — so the row has to carry every
     * field the window edits, or saving quietly blanks it.
     */
    public function test_editing_from_the_list_keeps_what_was_not_changed(): void
    {
        $user = $this->encoder();
        $customer = Customer::create([
            "name" => "Old Name", "address" => "Old Address", "contact_number" => "09000000000",
            "email" => "old@example.com", "is_active" => 1, "is_regular" => 1, "is_blacklisted" => 0,
            "credit_limit" => 25000, "added_by_id" => $user->id,
        ]);

        $row = collect($this->actingAs($user)->getJson("/customers?option=lists&count=10")->assertOk()->json("data"))
            ->firstWhere("id", $customer->id);

        // What Modals/Create.vue edit() copies out of the row.
        $form = [
            "id" => $row["id"],
            "name" => "New Name",
            "address" => $row["address"],
            "contact_number" => $row["contact_number"],
            "email" => $row["email"],
            "is_regular" => $row["is_regular"],
            "is_active" => $row["is_active"],
            "is_blacklisted" => $row["is_blacklisted"],
            "credit_limit" => $row["credit_limit"] ?? 0,
        ];

        $this->actingAs($user)->from("/customers")->put("/customers/{$customer->id}", $form)
            ->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertSame("New Name", $customer->name);
        $this->assertEquals(25000, (float) $customer->credit_limit);
        $this->assertSame(1, (int) $customer->is_regular);
    }
}
