<?php

use Inertia\Testing\AssertableInertia as Assert;

test('default locale is croatian', function () {
    config()->set('app.locale', 'hr');
    session()->forget('locale');

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'hr')
            ->where('availableLocales.0.code', 'hr')
            ->where('availableLocales.1.code', 'en'),
        );
});

test('user can switch locale to english', function () {
    $this->from(route('home'))
        ->post(route('locale.update'), [
            'locale' => 'en',
        ])
        ->assertRedirect(route('home'));

    expect(session('locale'))->toBe('en');

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});

test('locale update validates locale value', function () {
    $this->from(route('home'))
        ->post(route('locale.update'), [
            'locale' => 'de',
        ])
        ->assertSessionHasErrors(['locale']);
});
