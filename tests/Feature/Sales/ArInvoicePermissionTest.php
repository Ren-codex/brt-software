<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArInvoicePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        foreach (['for-payment', 'unpaid'] as $slug) {
            ListStatus::firstOrCreate(['slug' => $slug], [
                'name' => ucwords(str_replace('-', ' ', $slug)), 'text_color' => '#fff', 'bg_color' => '#333',
            ]);
        }
    }

    private function userWithGrant(?string $submoduleKey, string $level): User
    {
        $role = ListRole::create(['name' => 'Test Role ' . uniqid(), 'type' => 'role', 'definition' => 'test', 'is_active' => true]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'sales')->firstOrFail();
        $submoduleId = null;
        if ($submoduleKey) {
            $submoduleId = $module->submodules()->where('key', $submoduleKey)->firstOrFail()->id;
        }

        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $submoduleId, 'access_level' => $level,
        ]);

        return $user;
    }

    private function makeInvoice(): ArInvoice
    {
        $order = SalesOrder::create([
            'so_number' => 'SO-TEST-' . uniqid(),
            'payment_mode' => 'Credit',
            'order_date' => now()->toDateString(),
            'added_by_id' => User::factory()->create()->id,
            'status_id' => ListStatus::where('slug', 'for-payment')->first()->id,
        ]);

        return ArInvoice::create([
            'sales_order_id' => $order->id,
            'status_id' => ListStatus::where('slug', 'unpaid')->first()->id,
            'invoice_number' => 'INV-TEST-' . uniqid(),
            'invoice_date' => now()->toDateString(),
            'amount_due' => 1000,
            'amount_paid' => 0,
            'balance_due' => 1000,
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->userWithGrant('sales_orders', 'view');

        $this->actingAs($user)
            ->getJson('/ar-invoices?option=lists')
            ->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->userWithGrant('ar_invoices', 'view');

        $this->actingAs($user)
            ->getJson('/ar-invoices?option=lists')
            ->assertOk();
    }

    public function test_payment_denied_without_encoder_grant(): void
    {
        $invoice = $this->makeInvoice();
        $user = $this->userWithGrant('ar_invoices', 'view'); // view only, not encoder

        $this->actingAs($user)
            ->put("/ar-invoices/{$invoice->id}", [
                'option' => 'payment',
                'amount_paid' => 500,
                'balance_due' => 1000,
                'payment_date' => now()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_payment_allowed_with_encoder_grant(): void
    {
        $invoice = $this->makeInvoice();
        $user = $this->userWithGrant('ar_invoices', 'encoder');

        $response = $this->actingAs($user)
            ->put("/ar-invoices/{$invoice->id}", [
                'option' => 'payment',
                'amount_paid' => 500,
                'balance_due' => 1000,
                'payment_date' => now()->toDateString(),
            ]);

        // Proves the request wasn't blocked by the 403 authorization gate —
        // the redirect-back outcome (success or a business-logic error) is
        // covered by this codebase's existing payment tests elsewhere.
        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
