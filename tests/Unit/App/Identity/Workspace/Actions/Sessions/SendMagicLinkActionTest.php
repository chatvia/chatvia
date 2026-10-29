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
function smlSentSignIn(string $email, ?Agent $agent = null): ?MagicLinkSignIn
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

it('sends a dashboard link to the staff account with the email, whatever its case and spacing', function (): void {
    Notification::fake();
    User::factory()->create(['email' => 'user@example.test']);

    app(SendMagicLinkAction::class)->execute(
        new SendMagicLinkData(email: '  USER@Example.test  '),
        redirectUrl: '/dashboard',
    );

    Notification::assertSentOnDemand(
        MagicLinkNotification::class,
        fn (MagicLinkNotification $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'user@example.test',
    );
});

it('silently sends nothing to an unknown, deactivated or unreachable account', function (): void {
    Notification::fake();
    $agent = createAgent(User::factory()->workspace()->create());
    Chatter::factory()->create(['email' => 'chatter-only@example.test']);
    User::factory()->create(['email' => 'off@example.test', 'deactivated_at' => now()]);
    User::factory()->workspace()->create(['email' => 'outsider@example.test']);
    Chatter::factory()->ofWorkspace($agent->workspace)->create(['email' => 'ungranted@example.test']);
    Chatter::factory()->grantedTo($agent)->deactivated()->create(['email' => 'off-chatter@example.test']);
    $send = fn (string $email, ?Agent $scope = null) => app(SendMagicLinkAction::class)->execute(
        new SendMagicLinkData(email: $email),
        redirectUrl: '/dashboard',
        agent: $scope,
    );

    $send('unknown@example.test');
    $send('chatter-only@example.test');
    $send('off@example.test');
    $send('outsider@example.test', $agent);
    $send('ungranted@example.test', $agent);
    $send('off-chatter@example.test', $agent);

    Notification::assertNothingSent();
});

it('signs the workspace\'s chatter in to the agent\'s chat ahead of staff with the same email, otherwise staff who can reach it', function (): void {
    $owner = User::factory()->workspace()->create(['email' => 'shared@example.test']);
    $agent = createAgent($owner);
    $chatter = Chatter::factory()->grantedTo($agent)->create(['email' => 'shared@example.test']);
    $staffOwner = User::factory()->workspace()->create(['email' => 'owner@example.test']);
    $staffAgent = createAgent($staffOwner);

    $chatterSignIn = smlSentSignIn('  SHARED@example.test ', $agent);
    $staffSignIn = smlSentSignIn('owner@example.test', $staffAgent);

    expect($chatterSignIn?->chatterId)->toBe($chatter->id)
        ->and($chatterSignIn?->userId)->toBeNull()
        ->and($chatterSignIn?->agentId)->toBe($agent->id)
        ->and($staffSignIn?->userId)->toBe($staffOwner->id)
        ->and($staffSignIn?->chatterId)->toBeNull()
        ->and($staffSignIn?->agentId)->toBe($staffAgent->id);
});
