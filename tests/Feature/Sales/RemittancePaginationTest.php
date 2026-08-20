<?php

namespace Tests\Feature\Sales;

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

/**
 * The Remittances list passes its Pagination component a next/prev link, but
 * the component's fetch() accepted that URL and then ignored it — every page
 * button silently refetched page one. This pins the API contract the fixed
 * frontend relies on: the paginator must expose working page links.
 */
class RemittancePaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);

        $status = ListStatus::firstOrCreate(['slug' => 'for-verification'], [
            'name' => 'For Verification', 'text_color' => '#fff', 'bg_color' => '#333',
        ]);

        $role = ListRole::create(['name' => 'Remit Viewer', 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        $this->user = User::factory()->create();
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $this->user->id]);

        $module = Module::where('key', 'sales')->firstOrFail();
        RolePermission::create([
            'role_id'      => $role->id,
            'module_id'    => $module->id,
            'submodule_id' => $module->submodules()->where('key', 'remittances')->firstOrFail()->id,
            'access_level' => 'view',
        ]);

        foreach (range(1, 16) as $i) {
            Remittance::create([
                'remittance_no'   => 'RM-'.str_pad($i, 3, '0', STR_PAD_LEFT),
                'remittance_date' => now()->toDateString(),
                'total_amount'    => 100 * $i,
                'received_amount' => 0,
                'variance'        => 0,
                'summary'         => json_encode([]),
                'status_id'       => $status->id,
                'created_by_id'   => $this->user->id,
            ]);
        }
    }

    public function test_second_page_returns_different_records(): void
    {
        $this->actingAs($this->user);

        $one = $this->getJson('/remittances?option=lists&count=10')->assertOk();
        $this->assertSame(1, $one->json('meta.current_page'));
        $this->assertCount(10, $one->json('data'));
        $this->assertSame(16, $one->json('meta.total'));

        $next = $one->json('links.next');
        $this->assertNotNull($next, 'Paginator must expose a next link for the UI to follow.');
        $this->assertStringContainsString('page=2', $next);

        // The paginator's link carries only ?page=N. The frontend passes that
        // URL to axios together with its params, so option=lists is re-applied —
        // this mirrors that rather than following the bare link.
        $two = $this->getJson($next.'&option=lists&count=10')->assertOk();
        $this->assertSame(2, $two->json('meta.current_page'));

        $firstPageIds = collect($one->json('data'))->pluck('id')->all();
        $secondPageIds = collect($two->json('data'))->pluck('id')->all();

        $this->assertNotEquals($firstPageIds, $secondPageIds, 'Page 2 must not repeat page 1.');
        $this->assertEmpty(
            array_intersect($firstPageIds, $secondPageIds),
            'Pages must not overlap.'
        );
    }
}
