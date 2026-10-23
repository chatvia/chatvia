<?php

use App\Http\Auth\MagicLinkSignIn;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\Sessions\SendMagicLinkAction;
use App\Identity\Workspace\Data\Sessions\SendMagicLinkData;
use App\Identity\Workspace\Events\MagicLinkRequested;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Notifications\MagicLinkNotification;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use MagicLink\MagicLink;

/**
 * Emailing a single-use sign-in link. Without an agent it is for a staff
 * account's dashboard; scoped to an agent it signs in to that agent's chat,
 * first as a chatter of the agent's workspace with that email, otherwise as
 * staff who can reach the agent. Every refusal is silent.
 */

/**
 * Sends a link for the email (scoped to the agent when given) and returns
 * the sign-in it carries, or null when none was sent.
 */
function sentMagicLinkSignIn(string $email, ?Agent $agent = null): ?MagicLinkSignIn
{
    Event::fake([MagicLinkRequested::class]);

    app(SendMagicLinkAction::class)->execute(
        new SendMagicLinkData(email: $email),
        redirectUrl: '/dashboard',
        agent: $agent,
    );

    $event = Event::dispatched(MagicLinkRequested::class)->last()[0] ?? null;

    if (! $event instanceof MagicLinkRequested) {
        return null;
    }

    $id = Str::before(Str::after(urldecode($event->url), '/magiclink/'), ':');

    return MagicLink::query()->findOrFail($id)->action;
}

it('sends a magic link to a user that matches the email', function (): void {
    Notification::fake();
    User::factory()->create(['email' => 'user@example.test']);

    app(SendMagicLinkAction::class)->execute(
        new SendMagicLinkData(email: 'user@example.test'),
        redirectUrl: '/dashboard',
    );

    Notification::assertSentOnDemand(MagicLinkNotification::class);
});

it('normalises the email (trim + lowercase) before lookup', function (): void {
    Notification::fake();
    User::factory()->create(['email' => 'mixed@example.test']);

    app(SendMagicLinkAction::class)->execute(
        new SendMagicLinkData(email: '  MIXED@example.test  '),
        redirectUrl: '/dashboard',
    );

    Notification::assertSentOnDemand(MagicLinkNotification::class);
});

it('silently no-ops when no user matches the email', function (): void {
    Notification::fake();

    app(SendMagicLinkAction::class)->execute(
        new SendMagicLinkData(email: 'unknown@example.test'),
        redirectUrl: '/dashboard',
    );

    Notification::assertNothingSent();
});

it('sends no dashboard link to a chatter', function (): void {
    Notification::fake();
    Chatter::factory()->create(['email' => 'chatter-only@example.test']);

    app(SendMagicLinkAction::class)->execute(
        new SendMagicLinkData(email: 'chatter-only@example.test'),
        redirectUrl: '/dashboard',
    );

    Notification::assertNothingSent();
});

it('silently no-ops for a staff account that cannot reach the scoped agent', function (): void {
    $agent = createAgent(User::factory()->workspace()->create());
    // A staff account is found by email in any workspace; the owner of another
    // workspace is found and then refused by canAccessAgent().
    User::factory()->workspace()->create(['email' => 'outsider@example.test']);

    expect(sentMagicLinkSignIn('outsider@example.test', $agent))->toBeNull();
});

it('silently no-ops for a chatter of the workspace who was not granted the agent', function (): void {
    Notification::fake();
    $agent = createAgent(User::factory()->workspace()->create());
    Chatter::factory()->ofWorkspace($agent->workspace)->create(['email' => 'ungranted@example.test']);

    app(SendMagicLinkAction::class)->execute(
        new SendMagicLinkData(email: 'ungranted@example.test'),
        redirectUrl: '/dashboard',
        agent: $agent,
    );

    Notification::assertNothingSent();
});

it('signs a chatter of the agent\'s workspace in to the agent\'s chat', function (): void {
    $agent = createAgent(User::factory()->workspace()->create());
    $chatter = Chatter::factory()->grantedTo($agent)->create(['email' => 'chatter@example.test']);

    $signIn = sentMagicLinkSignIn('  CHATTER@example.test ', $agent);

    expect($signIn)->not->toBeNull()
        ->and($signIn?->chatterId)->toBe($chatter->id)
        ->and($signIn?->userId)->toBeNull()
        ->and($signIn?->agentId)->toBe($agent->id);
});

it('prefers the workspace\'s chatter over a staff account with the same email', function (): void {
    $owner = User::factory()->workspace()->create(['email' => 'shared@example.test']);
    $agent = createAgent($owner);
    $chatter = Chatter::factory()->grantedTo($agent)->create(['email' => 'shared@example.test']);

    $signIn = sentMagicLinkSignIn('shared@example.test', $agent);

    expect($signIn?->chatterId)->toBe($chatter->id)
        ->and($signIn?->userId)->toBeNull();
});

it('signs staff who can reach the agent in to its chat when no chatter has the email', function (): void {
    $owner = User::factory()->workspace()->create(['email' => 'owner@example.test']);
    $agent = createAgent($owner);

    $signIn = sentMagicLinkSignIn('owner@example.test', $agent);

    expect($signIn?->userId)->toBe($owner->id)
        ->and($signIn?->chatterId)->toBeNull()
        ->and($signIn?->agentId)->toBe($agent->id);
});

it('silently no-ops when the account is deactivated', function (): void {
    Notification::fake();
    User::factory()->create([
        'email' => 'off@example.test',
        'deactivated_at' => now(),
    ]);

    app(SendMagicLinkAction::class)->execute(
        new SendMagicLinkData(email: 'off@example.test'),
        redirectUrl: '/dashboard',
    );

    Notification::assertNothingSent();
});

it('silently no-ops for a deactivated chatter', function (): void {
    $agent = createAgent(User::factory()->workspace()->create());
    Chatter::factory()->grantedTo($agent)->deactivated()->create(['email' => 'off-chatter@example.test']);

    expect(sentMagicLinkSignIn('off-chatter@example.test', $agent))->toBeNull();
});
