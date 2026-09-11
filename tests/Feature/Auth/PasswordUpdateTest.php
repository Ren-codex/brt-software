<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * PasswordController enforces a stricter policy than the Breeze default:
 * at least 8 characters, mixed case, a number and a symbol.
 */
test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'N3w-Passw0rd!',
            'password_confirmation' => 'N3w-Passw0rd!',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertTrue(Hash::check('N3w-Passw0rd!', $user->refresh()->password));
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'N3w-Passw0rd!',
            'password_confirmation' => 'N3w-Passw0rd!',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect('/profile');
});

test('a weak password is rejected', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

    $response->assertSessionHasErrors('password');

    $this->assertTrue(Hash::check('password', $user->refresh()->password));
});
