<?php

use App\Identity\Workspace\Listeners\RecordFailedLoginSecurityEvent;
use App\Identity\Workspace\Models\User;
use Domain\Security\Events\SecurityEventOccurred;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

/**
 * A failed sign-in is audited for the workspace of the account it was aimed
 * at, found by the attempted email when the guard did not resolve one. An
 * email no account has has no workspace to report to and is skipped. The
 * password is never recorded.
 */
beforeEach(function (): void {
    Event::fake([SecurityEventOccurred::class]);
    $this->request = Request::create('/login', 'POST', server: ['REMOTE_ADDR' => '203.0.113.5', 'HTTP_USER_AGENT' => 'curl/8.0']);
    $this->listener = new RecordFailedLoginSecurityEvent($this->request);
});

it('records a failed attempt for the account with the attempted email', function (): void {
    $user = User::factory()->create(['email' => 'dana@acme.test']);

    $this->listener->handle(new Failed('web', null, ['email' => 'dana@acme.test', 'password' => 'guess-1']));

    Event::assertDispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $event): bool => $event->action === 'auth.login_failed'
        && $event->workspaceId === $user->workspace()->id
        && $event->userId === $user->id
        && $event->outcome === 'failure'
        && $event->ip === '203.0.113.5'
        && json_decode($event->metadata, true) === ['email' => 'dana@acme.test']
        && ! str_contains($event->metadata, 'guess-1'));
});

it('records a failed attempt for the account the guard resolved', function (): void {
    $user = User::factory()->employee()->create();

    $this->listener->handle(new Failed('web', $user, ['email' => $user->email, 'password' => 'guess-1']));

    Event::assertDispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $event): bool => $event->workspaceId === $user->workspace()->id
        && $event->userId === $user->id);
});

it('skips an email that no account has', function (): void {
    $this->listener->handle(new Failed('web', null, ['email' => 'nobody@acme.test', 'password' => 'guess-1']));
    $this->listener->handle(new Failed('web', null, ['password' => 'guess-1']));

    Event::assertNotDispatched(SecurityEventOccurred::class);
});
