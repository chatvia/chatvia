<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Listeners\RecordLoginSecurityEvent;
use App\Identity\Workspace\Listeners\RecordLogoutSecurityEvent;
use App\Identity\Workspace\Models\User;
use Domain\Security\Events\SecurityEventOccurred;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

/**
 * A staff sign-in or sign-out goes to the workspace's security audit log with
 * the IP and browser it came from. A chatter's is not audited here.
 */
beforeEach(function (): void {
    Event::fake([SecurityEventOccurred::class]);
    $this->request = Request::create('/login', 'POST', server: ['REMOTE_ADDR' => '203.0.113.5', 'HTTP_USER_AGENT' => 'Firefox/130']);
});

it('records a staff sign-in for the user workspace', function (): void {
    $teammate = User::factory()->employee()->create();

    new RecordLoginSecurityEvent($this->request)->handle(new Login('web', $teammate, false));

    Event::assertDispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $event): bool => $event->action === 'auth.login'
        && $event->workspaceId === $teammate->workspace()->id
        && $event->userId === $teammate->id
        && $event->userEmail === $teammate->email
        && $event->ip === '203.0.113.5'
        && $event->userAgent === 'Firefox/130'
        && $event->outcome === 'success');
});

it('records nothing for a chatter', function (): void {
    new RecordLoginSecurityEvent($this->request)->handle(new Login('chat', Chatter::factory()->create(), false));

    Event::assertNotDispatched(SecurityEventOccurred::class);
});

it('records a staff sign-out for the user workspace, and nothing for a chatter', function (): void {
    $user = User::factory()->create();
    $listener = new RecordLogoutSecurityEvent($this->request);

    $listener->handle(new Logout('chat', Chatter::factory()->create()));
    $listener->handle(new Logout('web', $user));

    Event::assertDispatchedTimes(SecurityEventOccurred::class, 1);
    Event::assertDispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $event): bool => $event->action === 'auth.logout'
        && $event->workspaceId === $user->workspace()->id
        && $event->userId === $user->id
        && $event->ip === '203.0.113.5');
});
