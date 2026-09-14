<?php

namespace Tests\Feature\Permissions;

use App\Models\ListRole;
use App\Models\Module;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ModulesAndSubmodulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Choosing who may authorise a guarded action. The screen decides who holds the
 * keys, so it needs the same care as the keys themselves.
 */
class AuthorizationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModulesAndSubmodulesSeeder::class);
    }

    private function userWith(?string $level): User
    {
        $user = User::factory()->create();
        $role = ListRole::create(['name' => 'R' . uniqid(), 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        if ($level) {
            $module = Module::where('key', 'libraries')->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id, 'module_id' => $module->id,
                'submodule_id' => $module->submodules()->where('key', 'roles')->firstOrFail()->id,
                'access_level' => $level,
            ]);
        }

        return $user;
    }

    public function test_the_screen_is_closed_without_a_grant(): void
    {
        $this->actingAs($this->userWith(null))
            ->get('/libraries/authorization-settings')
            ->assertForbidden();
    }

    public function test_a_viewer_cannot_change_who_authorizes(): void
    {
        // Whoever can widen this can hand themselves the keys to every guarded
        // action, so it answers to admin, not view.
        $role = ListRole::create(['name' => 'Widened', 'type' => 'role', 'definition' => 't', 'is_active' => true]);

        $this->actingAs($this->userWith('view'))
            ->putJson('/libraries/authorization-settings', [
                'action' => 'checks.bounce',
                'role_ids' => [$role->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('supervisor_action_roles', ['role_id' => $role->id]);
    }

    public function test_an_admin_can_assign_a_role_to_an_action(): void
    {
        $role = ListRole::create(['name' => 'Area Business Manager', 'type' => 'role', 'definition' => 't', 'is_active' => true]);

        $this->actingAs($this->userWith('admin'))
            ->putJson('/libraries/authorization-settings', [
                'action' => 'checks.bounce',
                'role_ids' => [$role->id],
            ])
            ->assertOk();

        $this->assertDatabaseHas('supervisor_action_roles', [
            'action' => 'checks.bounce',
            'role_id' => $role->id,
        ]);
    }

    public function test_saving_replaces_rather_than_appends(): void
    {
        $first = ListRole::create(['name' => 'First', 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        $second = ListRole::create(['name' => 'Second', 'type' => 'role', 'definition' => 't', 'is_active' => true]);
        $admin = $this->userWith('admin');

        $this->actingAs($admin)->putJson('/libraries/authorization-settings', [
            'action' => 'checks.bounce', 'role_ids' => [$first->id],
        ])->assertOk();

        $this->actingAs($admin)->putJson('/libraries/authorization-settings', [
            'action' => 'checks.bounce', 'role_ids' => [$second->id],
        ])->assertOk();

        $this->assertDatabaseMissing('supervisor_action_roles', ['action' => 'checks.bounce', 'role_id' => $first->id]);
        $this->assertDatabaseHas('supervisor_action_roles', ['action' => 'checks.bounce', 'role_id' => $second->id]);
    }

    public function test_an_unguarded_action_cannot_be_configured(): void
    {
        $role = ListRole::create(['name' => 'Sneaky', 'type' => 'role', 'definition' => 't', 'is_active' => true]);

        $this->actingAs($this->userWith('admin'))
            ->putJson('/libraries/authorization-settings', [
                'action' => 'accounting.delete_everything',
                'role_ids' => [$role->id],
            ])
            ->assertStatus(422);

        $this->assertSame(0, DB::table('supervisor_action_roles')->where('action', 'accounting.delete_everything')->count());
    }
}
