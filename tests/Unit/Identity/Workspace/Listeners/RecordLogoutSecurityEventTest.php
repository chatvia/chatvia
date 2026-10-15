<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Listeners\RecordLogoutSecurityEvent;
use App\Identity\Workspace\Models\User;
use Domain\Security\Events\SecurityEventOccurred;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

/**
 * A staff sign-out goes to the workspace's security audit log with the IP and
 * browser it came from. A chatter sign-out is not audited here.
 */
beforeEach(function (): void {
    Event::fake([SecurityEventOccurred::class]);
    $this->request = Request::create('/logout', 'POST', server: ['REMOTE_ADDR' => '203.0.113.5', 'HTTP_USER_AGENT' => 'Firefox/130']);
});

it('records a staff sign-out for the user workspace', function (): void {
    $user = User::factory()->create();

    new RecordLogoutSecurityEvent($this->request)->handle(new Logout('web', $user));

    Event::assertDispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $event): bool => $event->action === 'auth.logout'
        && $event->workspaceId === $user->workspace()->id
        && $event->userId === $user->id
        && $event->ip === '203.0.113.5'
        && $event->userAgent === 'Firefox/130');
});

it('records nothing for a chatter', function (): void {
    new RecordLogoutSecurityEvent($this->request)->handle(new Logout('chat', Chatter::factory()->create()));

    Event::assertNotDispatched(SecurityEventOccurred::class);
});
