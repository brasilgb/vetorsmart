<?php

use App\Models\Segment;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;

/**
 * The authentication e-mails go through the queue, so the request never depends on the mail server being reachable.
 */
function queuedNotificationOf(string $class): Closure
{
    return fn (SendQueuedNotifications $job): bool => $job->notification instanceof $class;
}

test('registration completes and queues the confirmation link instead of sending it during the request', function () {
    Queue::fake();
    $segment = Segment::factory()->create();

    $this->post(route('register.store'), [
        'company_name' => 'Empresa Fila',
        'segment_id' => $segment->id,
        'name' => 'Pessoa Fila',
        'email' => 'fila@example.com',
        'password' => 'Senha-Forte-2026',
        'password_confirmation' => 'Senha-Forte-2026',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    Queue::assertPushed(SendQueuedNotifications::class, queuedNotificationOf(VerifyEmailNotification::class));
});

test('the password reset request is answered and the link is queued', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');

    Queue::assertPushed(SendQueuedNotifications::class, queuedNotificationOf(ResetPasswordNotification::class));
});

test('resending the confirmation is answered and the link is queued', function () {
    Queue::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->post(route('verification.send'))->assertSessionHas('status', 'verification-link-sent');

    Queue::assertPushed(SendQueuedNotifications::class, queuedNotificationOf(VerifyEmailNotification::class));
});

test('a failing delivery is retried before being kept as a failed job', function () {
    expect((new VerifyEmailNotification)->tries)->toBe(3)
        ->and((new ResetPasswordNotification('token'))->tries)->toBe(3)
        ->and((new VerifyEmailNotification)->backoff())->toBe([60, 300]);
});
