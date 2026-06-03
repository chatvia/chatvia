<?php

use App\Identity\Workspace\Actions\Sessions\SendMagicLinkAction;
use App\Identity\Workspace\Data\Sessions\SendMagicLinkData;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Notifications\MagicLinkNotification;
use Illuminate\Support\Facades\Notification;

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

it('silently no-ops when the user cannot access the scoped agent', function (): void {
    Notification::fake();
    $workspace = User::factory()->workspace()->create();
    $otherWorkspace = User::factory()->workspace()->create();
    $chatter = User::factory()->chatter($otherWorkspace)->create([
        'email' => 'outsider@example.test',
    ]);
    $agent = createAgent($workspace);

    app(SendMagicLinkAction::class)->execute(
        new SendMagicLinkData(email: 'outsider@example.test'),
        redirectUrl: '/dashboard',
        agent: $agent,
    );

    Notification::assertNothingSent();
});
