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
 * Choosing who may authorise a guarded action. This is set per role, alongside
 * that role's other access, and decides who holds the keys -- so it needs the
 * same care as the keys themselves.
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

    private function subject(string $name = 'Area Business Manager'): ListRole
    {
        return ListRole::create(['name' => $name, 'type' => 'role', 'definition' => 't', 'is_active' => true]);
    }

    public function test_the_actions_are_listed_with_the_role(): void
    {
        $role = $this->subject();
        DB::table('supervisor_action_roles')->insert([
            'action' => 'checks.bounce', 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->userWith('view'))
            ->getJson("/libraries/roles/{$role->id}/permissions")
            ->assertOk();

        $actions = collect($response->json('authorizations'))->keyBy('key');

        $this->assertTrue($actions['checks.bounce']['assigned']);
        $this->assertFalse($actions['sales.credit_sale']['assigned']);
        $this->assertSame('Record a bounced check', $actions['checks.bounce']['label']);
    }

    public function test_a_viewer_cannot_change_who_authorizes(): void
    {
        // Whoever can widen this can hand themselves the keys to every guarded
        // action, so it answers to admin, not view.
        $role = $this->subject('Widened');

        $this->actingAs($this->userWith('view'))
            ->postJson("/libraries/roles/{$role->id}/permissions", [
                'grants' => [],
                'authorizations' => ['checks.bounce'],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('supervisor_action_roles', ['role_id' => $role->id]);
    }

    public function test_an_admin_can_assign_an_action_to_a_role(): void
    {
        $role = $this->subject();

        $this->actingAs($this->userWith('admin'))
            ->postJson("/libraries/roles/{$role->id}/permissions", [
                'grants' => [],
                'authorizations' => ['checks.bounce'],
            ])
            ->assertOk();

        $this->assertDatabaseHas('supervisor_action_roles', [
            'action' => 'checks.bounce',
            'role_id' => $role->id,
        ]);
    }

    public function test_saving_replaces_rather_than_appends(): void
    {
        $role = $this->subject();
        $admin = $this->userWith('admin');

        $this->actingAs($admin)->postJson("/libraries/roles/{$role->id}/permissions", [
            'grants' => [], 'authorizations' => ['checks.bounce'],
        ])->assertOk();

        $this->actingAs($admin)->postJson("/libraries/roles/{$role->id}/permissions", [
            'grants' => [], 'authorizations' => ['sales.credit_sale'],
        ])->assertOk();

        $this->assertDatabaseMissing('supervisor_action_roles', ['action' => 'checks.bounce', 'role_id' => $role->id]);
        $this->assertDatabaseHas('supervisor_action_roles', ['action' => 'sales.credit_sale', 'role_id' => $role->id]);
    }

    public function test_unticking_everything_clears_the_role(): void
    {
        $role = $this->subject();
        $admin = $this->userWith('admin');

        $this->actingAs($admin)->postJson("/libraries/roles/{$role->id}/permissions", [
            'grants' => [], 'authorizations' => ['checks.bounce'],
        ])->assertOk();

        $this->actingAs($admin)->postJson("/libraries/roles/{$role->id}/permissions", [
            'grants' => [], 'authorizations' => [],
        ])->assertOk();

        $this->assertSame(0, DB::table('supervisor_action_roles')->where('role_id', $role->id)->count());
    }

    public function test_saving_one_role_leaves_another_alone(): void
    {
        $mine = $this->subject('Mine');
        $theirs = $this->subject('Theirs');
        DB::table('supervisor_action_roles')->insert([
            'action' => 'checks.bounce', 'role_id' => $theirs->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->userWith('admin'))
            ->postJson("/libraries/roles/{$mine->id}/permissions", [
                'grants' => [], 'authorizations' => [],
            ])->assertOk();

        $this->assertDatabaseHas('supervisor_action_roles', ['action' => 'checks.bounce', 'role_id' => $theirs->id]);
    }

    public function test_a_payload_that_omits_authorizations_leaves_them_untouched(): void
    {
        // What a stale cached bundle posting the old payload shape looks like.
        // Reading that silence as "none of them" would quietly strip a role's
        // authorising rights on an unrelated permissions save.
        $role = $this->subject();
        DB::table('supervisor_action_roles')->insert([
            'action' => 'checks.bounce', 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->userWith('admin'))
            ->postJson("/libraries/roles/{$role->id}/permissions", ['grants' => []])
            ->assertOk();

        $this->assertDatabaseHas('supervisor_action_roles', ['action' => 'checks.bounce', 'role_id' => $role->id]);
    }

    public function test_an_unguarded_action_cannot_be_configured(): void
    {
        $role = $this->subject('Sneaky');

        $this->actingAs($this->userWith('admin'))
            ->postJson("/libraries/roles/{$role->id}/permissions", [
                'grants' => [],
                'authorizations' => ['accounting.delete_everything'],
            ])
            ->assertStatus(422);

        $this->assertSame(0, DB::table('supervisor_action_roles')->where('action', 'accounting.delete_everything')->count());
    }
}
