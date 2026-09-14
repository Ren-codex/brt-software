<?php

namespace Tests\Feature\Permissions;

use App\Models\ListRole;
use App\Models\User;
use App\Models\UserRole;
use App\Services\System\Permission\SupervisorAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * A supervisor override only means something if it can refuse. The typed-word
 * gates it replaces were checked in the browser, so anyone posting directly
 * walked past them; these checks run on the server and the action cannot
 * proceed without a token this class issued.
 */
class SupervisorAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const ACTION = 'sales.cancel_order';

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('supervisor|admin01|127.0.0.1');
    }

    private function userWithRole(string $roleName, string $username, string $password = 'secret-password'): User
    {
        $user = User::factory()->create([
            'username' => $username,
            'password' => Hash::make($password),
        ]);
        $role = ListRole::firstOrCreate(['name' => $roleName], ['type' => 'role', 'definition' => 't', 'is_active' => true]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);

        return $user;
    }

    private function authorize(string $username, string $password, string $action = self::ACTION): string
    {
        return app(SupervisorAuthorization::class)->issue($username, $password, $action, '127.0.0.1');
    }

    public function test_an_administrator_with_the_right_password_gets_a_token(): void
    {
        $this->userWithRole('Administrator', 'admin01');

        $this->assertNotEmpty($this->authorize('admin01', 'secret-password'));
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $this->userWithRole('Administrator', 'admin01');

        $this->expectException(ValidationException::class);
        $this->authorize('admin01', 'not-the-password');
    }

    public function test_an_unknown_username_is_refused(): void
    {
        $this->expectException(ValidationException::class);
        $this->authorize('nobody', 'secret-password');
    }

    public function test_a_non_administrator_is_refused_even_with_the_right_password(): void
    {
        // The whole point: valid credentials are not authority.
        $this->userWithRole('Sales Rep', 'rep01');

        $this->expectException(ValidationException::class);
        $this->authorize('rep01', 'secret-password');
    }

    public function test_an_administrator_whose_role_was_deactivated_is_refused(): void
    {
        $user = $this->userWithRole('Administrator', 'admin01');
        UserRole::where('user_id', $user->id)->update(['is_active' => 0]);

        $this->expectException(ValidationException::class);
        $this->authorize('admin01', 'secret-password');
    }

    public function test_a_token_works_once_only(): void
    {
        $this->userWithRole('Administrator', 'admin01');
        $token = $this->authorize('admin01', 'secret-password');
        $svc = app(SupervisorAuthorization::class);

        $this->assertNotNull($svc->consume($token, self::ACTION));

        $this->expectException(ValidationException::class);
        $svc->consume($token, self::ACTION);
    }

    public function test_a_token_cannot_be_spent_on_a_different_action(): void
    {
        $this->userWithRole('Administrator', 'admin01');
        $token = $this->authorize('admin01', 'secret-password');

        $this->expectException(ValidationException::class);
        app(SupervisorAuthorization::class)->consume($token, 'sales.approve_return');
    }

    public function test_repeated_wrong_passwords_are_throttled(): void
    {
        $this->userWithRole('Administrator', 'admin01');

        for ($i = 0; $i < 5; $i++) {
            try { $this->authorize('admin01', 'wrong'); } catch (ValidationException $e) { /* expected */ }
        }

        // Even the correct password is now refused: otherwise this endpoint is a
        // password oracle any logged-in rep could hammer.
        $this->expectException(ValidationException::class);
        $this->authorize('admin01', 'secret-password');
    }

    public function test_the_endpoint_refuses_an_action_it_does_not_guard(): void
    {
        $this->userWithRole('Administrator', 'admin01');

        $this->actingAs(User::factory()->create())
            ->postJson('/supervisor-authorization', [
                'username' => 'admin01',
                'password' => 'secret-password',
                'action' => 'sales.delete_everything',
            ])
            ->assertStatus(422);
    }

    public function test_the_endpoint_never_returns_a_token_to_a_non_administrator(): void
    {
        $this->userWithRole('Sales Rep', 'rep01');

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/supervisor-authorization', [
                'username' => 'rep01',
                'password' => 'secret-password',
                'action' => self::ACTION,
            ])->assertStatus(422);

        $this->assertStringNotContainsString('token', $response->getContent());
    }

    public function test_a_role_assigned_to_the_action_may_authorize_it(): void
    {
        $this->userWithRole('Area Business Manager', 'abm01');
        $this->assignRole('Area Business Manager', self::ACTION);

        $this->assertNotEmpty($this->authorize('abm01', 'secret-password'));
    }

    public function test_assigning_a_role_to_one_action_does_not_widen_another(): void
    {
        $this->userWithRole('Area Business Manager', 'abm01');
        $this->assignRole('Area Business Manager', 'checks.bounce');

        $this->expectException(ValidationException::class);
        $this->authorize('abm01', 'secret-password', self::ACTION);
    }

    public function test_administrator_still_authorizes_when_nothing_is_configured(): void
    {
        // An action nobody has configured falls back to Administrator rather
        // than becoming impossible to perform.
        $this->userWithRole('Administrator', 'admin01');
        \Illuminate\Support\Facades\DB::table('supervisor_action_roles')->delete();

        $this->assertNotEmpty($this->authorize('admin01', 'secret-password'));
    }

    public function test_super_admin_can_always_authorize(): void
    {
        // A configuration that excluded everyone would otherwise lock the action
        // away with no way back in.
        $this->userWithRole('Super Admin', 'root01');
        \Illuminate\Support\Facades\DB::table('supervisor_action_roles')->delete();
        $this->assignRole('Warehouse Manager', self::ACTION);

        $this->assertNotEmpty($this->authorize('root01', 'secret-password'));
    }

    /** Let a role authorise one action, the way the settings screen would. */
    private function assignRole(string $roleName, string $action): void
    {
        $role = ListRole::firstOrCreate(['name' => $roleName], ['type' => 'role', 'definition' => 't', 'is_active' => true]);
        \Illuminate\Support\Facades\DB::table('supervisor_action_roles')->insert([
            'action' => $action, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_consuming_a_token_records_who_authorized_it(): void
    {
        $admin = $this->userWithRole('Administrator', 'admin01');
        $svc = app(SupervisorAuthorization::class);

        $svc->consume($this->authorize('admin01', 'secret-password'), self::ACTION);

        $this->assertDatabaseHas('supervisor_authorizations', [
            'authorized_by_id' => $admin->id,
            'action' => self::ACTION,
        ]);
    }
}
