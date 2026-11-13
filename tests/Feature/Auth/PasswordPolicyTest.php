<?php

use App\Models\Segment;
use App\Models\User;
use Database\Seeders\SegmentSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Inertia\Testing\AssertableInertia;

/**
 * @return array<string, string|int>
 */
function simplePasswordRegistration(string $password): array
{
    return [
        'company_name' => 'Clima Total',
        'segment_id' => Segment::query()->value('id'),
        'name' => 'Marta',
        'email' => 'marta@example.com',
        'password' => $password,
        'password_confirmation' => $password,
    ];
}

test('registration accepts simple passwords of at least 8 characters', function (string $password) {
    $this->seed(SegmentSeeder::class);

    $this->post(route('register.store'), simplePasswordRegistration($password))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    expect(Hash::check($password, User::query()->where('email', 'marta@example.com')->value('password')))->toBeTrue();
})->with([
    'digits only' => '12345678',
    'lowercase letters only' => 'abcdefgh',
    'long with symbols' => 'uma frase longa de senha com símbolos #2026!',
]);

test('registration rejects a password shorter than 8 characters', function () {
    $this->seed(SegmentSeeder::class);

    $this->post(route('register.store'), simplePasswordRegistration('1234567'))
        ->assertSessionHasErrors(['password' => 'O campo senha deve ter pelo menos 8 caracteres.']);

    expect(User::query()->where('email', 'marta@example.com')->exists())->toBeFalse();
});

test('a common user can reset the password to 8 digits', function () {
    $user = User::factory()->create();

    $this->post(route('password.update'), [
        'token' => PasswordBroker::createToken($user),
        'email' => $user->email,
        'password' => '87654321',
        'password_confirmation' => '87654321',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('87654321', $user->refresh()->password))->toBeTrue();
});

test('a root admin can reset the password to 8 digits', function () {
    $root = User::factory()->rootAdmin()->create();

    $this->post(route('password.update'), [
        'token' => PasswordBroker::createToken($root),
        'email' => $root->email,
        'password' => '87654321',
        'password_confirmation' => '87654321',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('87654321', $root->refresh()->password))->toBeTrue();
});

test('a common user can change the password to 8 digits in settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => '11223344',
            'password_confirmation' => '11223344',
        ])->assertSessionHasNoErrors();

    expect(Hash::check('11223344', $user->refresh()->password))->toBeTrue();
});

test('a root admin can change the password to 8 digits in settings', function () {
    $root = User::factory()->rootAdmin()->create();

    $this->actingAs($root)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => '11223344',
            'password_confirmation' => '11223344',
        ])->assertSessionHasNoErrors();

    expect(Hash::check('11223344', $root->refresh()->password))->toBeTrue();
});

test('a password shorter than 8 characters is rejected in settings for a root admin', function () {
    $root = User::factory()->rootAdmin()->create();

    $this->actingAs($root)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => '1234567',
            'password_confirmation' => '1234567',
        ])->assertSessionHasErrors('password');

    expect(Hash::check('1234567', $root->refresh()->password))->toBeFalse();
});

test('the registration screen hints the simple password rule to the browser', function () {
    $this->seed(SegmentSeeder::class);

    $this->get(route('register'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('auth/register')
            ->where('passwordRules', 'minlength: 8;'));
});
