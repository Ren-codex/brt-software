<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('a guest cannot view the profile page', function () {
    $this->get('/profile')->assertRedirect('/login');
});

/**
 * Breeze's self-service profile endpoints (PATCH /profile to change your own
 * name and email, DELETE /profile to delete your own account) are deliberately
 * not part of this system — employee records are maintained through
 * EmployeeController, and accounts are deactivated by an administrator rather
 * than self-deleted. These pin that divergence so the endpoints are not
 * reintroduced without a decision.
 */
test('the breeze self-service profile update endpoint is not exposed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile', ['name' => 'Test User', 'email' => 'test@example.com'])
        ->assertMethodNotAllowed();
});

test('a user cannot delete their own account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete('/profile', ['password' => 'password'])
        ->assertMethodNotAllowed();

    $this->assertNotNull($user->fresh());
});

function profileEmployee(User $user, array $attributes = []): \App\Models\Employee
{
    return \App\Models\Employee::create(array_merge([
        'firstname' => 'Ana',
        'lastname' => 'Cruz',
        'mobile' => '09170000000',
        'birthdate' => '1990-01-01',
        'sex' => 'Female',
        'religion' => 'None',
        'user_id' => $user->id,
    ], $attributes));
}

test('a user can update their own personal information', function () {
    $user = User::factory()->create();
    $employee = profileEmployee($user);

    $this->actingAs($user)
        ->put('/profile/updated', [
            'firstname' => 'Maria',
            'middlename' => 'Bautista',
            'lastname' => 'Santos',
            'suffix' => 'Jr.',
            'gender' => 'Male',
            'mobile' => '09171234567',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', true)
        ->assertSessionHas('message', 'Personal information updated successfully.')
        ->assertRedirect();

    $employee->refresh();
    expect($employee->firstname)->toBe('Maria')
        ->and($employee->middlename)->toBe('Bautista')
        ->and($employee->lastname)->toBe('Santos')
        ->and($employee->suffix)->toBe('Jr.')
        ->and($employee->sex)->toBe('Male')
        ->and($employee->mobile)->toBe('09171234567');
});

test('a profile update rejects a malformed or taken mobile number', function () {
    $user = User::factory()->create();
    profileEmployee($user);
    profileEmployee(User::factory()->create(), ['mobile' => '09179999999']);

    $payload = ['firstname' => 'Ana', 'lastname' => 'Cruz', 'gender' => 'Female'];

    $this->actingAs($user)
        ->put('/profile/updated', $payload + ['mobile' => '12345'])
        ->assertSessionHasErrors('mobile');

    $this->actingAs($user)
        ->put('/profile/updated', $payload + ['mobile' => '09179999999'])
        ->assertSessionHasErrors('mobile');
});

test('a non-admin must fill in name, mobile and gender', function () {
    $user = User::factory()->create();
    profileEmployee($user);

    $this->actingAs($user)
        ->put('/profile/updated', [])
        ->assertSessionHasErrors(['firstname', 'lastname', 'mobile', 'gender']);
});

test('an admin can save the profile with fields left blank, keeping what is on file', function () {
    $user = User::factory()->create();
    $role = \App\Models\ListRole::create(['name' => 'Administrator', 'type' => 'role', 'definition' => 't', 'is_active' => true]);
    \App\Models\UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);
    $employee = profileEmployee($user, ['middlename' => 'Bautista']);

    $this->actingAs($user)
        ->put('/profile/updated', ['firstname' => 'Maria'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', true);

    $employee->refresh();
    expect($employee->firstname)->toBe('Maria')
        ->and($employee->lastname)->toBe('Cruz')
        ->and($employee->mobile)->toBe('09170000000')
        ->and($employee->sex)->toBe('Female')
        ->and($employee->middlename)->toBeNull();
});

function giveRole(User $user, string $name): void
{
    $role = \App\Models\ListRole::firstOrCreate(['name' => $name], ['type' => 'role', 'definition' => 't', 'is_active' => true]);
    \App\Models\UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => 1, 'added_by_id' => $user->id]);
}

test('a super admin without an employee record gets one from the profile page', function () {
    $user = User::factory()->create();
    giveRole($user, 'Super Admin');

    $this->actingAs($user)
        ->put('/profile/updated', ['firstname' => 'Super', 'lastname' => 'Admin'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', true);

    $employee = $user->fresh()->employee;
    expect($employee)->not->toBeNull()
        ->and($employee->firstname)->toBe('Super')
        ->and($employee->lastname)->toBe('Admin')
        ->and($employee->mobile)->toBe('');
});

test('a super admin can save with nothing filled in and a mobile in any format', function () {
    $user = User::factory()->create();
    giveRole($user, 'Super Admin');
    $employee = profileEmployee($user, ['mobile' => '+63 917 000 0000']);

    $this->actingAs($user)
        ->put('/profile/updated', ['mobile' => '+63 917 000 0000'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', true);

    expect($employee->fresh()->firstname)->toBe('Ana');
});

test('a non-admin without an employee record is told so', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/profile/updated', ['firstname' => 'Ana', 'lastname' => 'Cruz', 'mobile' => '09171234567', 'gender' => 'Female'])
        ->assertSessionHasErrors('firstname');

    expect($user->fresh()->employee)->toBeNull();
});
