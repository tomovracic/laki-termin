<?php

test('inertia shell includes csrf meta token', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('meta name="csrf-token" content="', false);
});
