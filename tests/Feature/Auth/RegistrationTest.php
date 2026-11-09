<?php

use App\Models\Segment;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    Segment::factory()->create();

    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $segment = Segment::factory()->create();

    $response = $this->post(route('register.store'), [
        'company_name' => 'Empresa Teste Ltda',
        'segment_id' => $segment->id,
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});
