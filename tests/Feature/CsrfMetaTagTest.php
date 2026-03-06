<?php

test('inertia shell includes csrf meta token', function () {
    $response = $this->get('/login');

    $response->assertOk();
    $response->assertSee('meta name="csrf-token" content="', false);
});

test('web response sets xsrf token cookie', function () {
    $response = $this->get('/login');

    $response->assertOk();
    $response->assertCookie('XSRF-TOKEN');
});
