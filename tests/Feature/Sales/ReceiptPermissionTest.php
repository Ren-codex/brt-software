<?php

namespace Tests\Feature\Sales;

use App\Models\ArInvoice;
use App\Models\ListRole;
use App\Models\ListStatus;
use App\Models\Module;
use App\Models\Receipt;
use App\Models\RolePermission;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        foreach (['for-payment', 'unpaid', 'pending'] as $slug) {
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
        $submoduleId = $submoduleKey ? $module->submodules()->where('key', $submoduleKey)->firstOrFail()->id : null;

        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $submoduleId, 'access_level' => $level,
        ]);

        return $user;
    }

    private function makeReceipt(): Receipt
    {
        $order = SalesOrder::create([
            'so_number' => 'SO-TEST-' . uniqid(), 'payment_mode' => 'Cash',
            'order_date' => now()->toDateString(), 'added_by_id' => User::factory()->create()->id,
            'status_id' => ListStatus::where('slug', 'for-payment')->first()->id,
        ]);
        $invoice = ArInvoice::create([
            'sales_order_id' => $order->id, 'status_id' => ListStatus::where('slug', 'unpaid')->first()->id,
            'invoice_number' => 'INV-TEST-' . uniqid(), 'invoice_date' => now()->toDateString(),
            'amount_due' => 500, 'amount_paid' => 0, 'balance_due' => 500,
        ]);

        return Receipt::create([
            'ar_invoice_id' => $invoice->id,
            'status_id' => ListStatus::where('slug', 'pending')->first()->id,
            'receipt_number' => 'RC-TEST-' . uniqid(),
            'receipt_date' => now()->toDateString(),
            'amount_paid' => 500,
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->userWithGrant('sales_orders', 'view');

        $this->actingAs($user)->getJson('/receipts?option=lists')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->userWithGrant('receipts', 'view');

        $this->actingAs($user)->getJson('/receipts?option=lists')->assertOk();
    }

    public function test_store_denied_without_encoder_grant(): void
    {
        $user = $this->userWithGrant('receipts', 'view');

        $response = $this->actingAs($user)->post('/receipts', []);

        $response->assertForbidden();
    }

    public function test_store_allowed_with_encoder_grant(): void
    {
        $user = $this->userWithGrant('receipts', 'encoder');

        $response = $this->actingAs($user)->post('/receipts', []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_destroy_denied_without_admin_grant(): void
    {
        $receipt = $this->makeReceipt();
        $user = $this->userWithGrant('receipts', 'encoder'); // encoder, not admin

        $this->actingAs($user)->delete("/receipts/{$receipt->id}")->assertForbidden();
    }

    public function test_destroy_allowed_with_admin_grant(): void
    {
        $receipt = $this->makeReceipt();
        $user = $this->userWithGrant('receipts', 'admin');

        $response = $this->actingAs($user)->delete("/receipts/{$receipt->id}");

        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
