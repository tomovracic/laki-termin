<?php

test('registration screen is not available', function () {
    $response = $this->get('/register');

    $response->assertNotFound();
});

test('public user registration is not available', function () {
    $response = $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'User',
        'phone' => '0911234567',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertNotFound();
    $this->assertGuest();
});
