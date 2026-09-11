<?php

/**
 * Self-registration is deliberately not exposed: accounts are provisioned by an
 * administrator through the Employees module. The Breeze registration routes
 * were removed, and these guard against them being reintroduced by accident.
 */
test('the registration screen is not exposed', function () {
    $this->get('/register')->assertNotFound();
});

test('a visitor cannot register themselves an account', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
});
