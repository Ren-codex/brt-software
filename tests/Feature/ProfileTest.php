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
