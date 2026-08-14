<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryStocks;
use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryStockPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function warehouseManagerWithGrant(?string $submoduleKey, string $level): User
    {
        $role = ListRole::firstOrCreate(['name' => 'Warehouse Manager'], [
            'type' => 'role', 'definition' => 'test', 'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        $module = Module::where('key', 'inventory')->firstOrFail();
        $submoduleId = $submoduleKey ? $module->submodules()->where('key', $submoduleKey)->firstOrFail()->id : null;

        RolePermission::create([
            'role_id' => $role->id, 'module_id' => $module->id,
            'submodule_id' => $submoduleId, 'access_level' => $level,
        ]);

        return $user;
    }

    private function makeStock(): InventoryStocks
    {
        return InventoryStocks::create([
            'batch_code' => 'BATCH-TEST-' . uniqid(),
            'quantity' => 10,
        ]);
    }

    public function test_list_denied_without_view_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('purchase_orders', 'view');

        $this->actingAs($user)->getJson('/inventory-stocks')->assertForbidden();
    }

    public function test_list_allowed_with_view_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('inventory_stocks', 'view');

        $this->actingAs($user)->getJson('/inventory-stocks')->assertOk();
    }

    public function test_update_price_denied_without_encoder_grant(): void
    {
        $stock = $this->makeStock();
        $user = $this->warehouseManagerWithGrant('inventory_stocks', 'view');

        $this->actingAs($user)->post("/inventory-stocks/{$stock->id}/update-price", [])->assertForbidden();
    }

    public function test_update_price_allowed_with_encoder_grant(): void
    {
        $stock = $this->makeStock();
        $user = $this->warehouseManagerWithGrant('inventory_stocks', 'encoder');

        $response = $this->actingAs($user)->post("/inventory-stocks/{$stock->id}/update-price", []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_adjustment_denied_without_encoder_grant(): void
    {
        $stock = $this->makeStock();
        $user = $this->warehouseManagerWithGrant('inventory_stocks', 'view');

        $this->actingAs($user)->post("/inventory-stocks/adjustment/{$stock->id}", [])->assertForbidden();
    }

    public function test_conversion_denied_without_encoder_grant(): void
    {
        $user = $this->warehouseManagerWithGrant('inventory_stocks', 'view');

        $this->actingAs($user)->post('/inventory-stocks/conversions', [])->assertForbidden();
    }

    public function test_weight_loss_denied_without_encoder_grant(): void
    {
        $stock = $this->makeStock();
        $user = $this->warehouseManagerWithGrant('inventory_stocks', 'view');

        $this->actingAs($user)->post("/inventory-stocks/{$stock->id}/weight-loss", [])->assertForbidden();
    }

    public function test_settings_denied_without_admin_grant(): void
    {
        $stock = $this->makeStock();
        $user = $this->warehouseManagerWithGrant('inventory_stocks', 'encoder');

        $this->actingAs($user)->post("/inventory-stocks/{$stock->id}/settings", [])->assertForbidden();
    }

    public function test_settings_allowed_with_admin_grant(): void
    {
        $stock = $this->makeStock();
        $user = $this->warehouseManagerWithGrant('inventory_stocks', 'admin');

        $response = $this->actingAs($user)->post("/inventory-stocks/{$stock->id}/settings", []);

        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
