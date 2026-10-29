<?php

use App\Identity\Chatter\Actions\BlockChatterAction;
use App\Identity\Chatter\Data\BlockChatterData;
use App\Identity\Chatter\Enums\ChatterBlockNotice;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Chatter\Notifications\ChatterBlockedNotification;
use Domain\Agent\Models\Agent;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Models\SecurityAuditLog;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Blocking a chatter switches their account off with the internal reason,
 * emails them only when asked (without that reason), and writes the block to
 * the security audit log.
 */
beforeEach(function (): void {
    Notification::fake();
    $this->agent = Agent::factory()->create();
    $this->chatter = Chatter::factory()->grantedTo($this->agent)->create();
});

function bcaBlock(ChatterBlockNotice $notice): void
{
    app(BlockChatterAction::class)->execute(new BlockChatterData(
        chatter: test()->chatter,
        notice: $notice,
        actor: SecurityActorData::forUser(test()->agent->workspace->owner),
        reason: 'Prompt injection, 12 times',
        agentId: test()->agent->id,
    ));
}

it('switches the account off, emails the chatter without the reason and audits the block', function (): void {
    bcaBlock(ChatterBlockNotice::Email);

    $chatter = $this->chatter->fresh();

    expect($chatter->isDeactivated())->toBeTrue()
        ->and($chatter->deactivation_reason)->toBe('Prompt injection, 12 times')
        ->and(SecurityAuditLog::query()
            ->where('workspace_id', $this->agent->workspace_id)
            ->where('action', 'chatter.blocked')
            ->where('target_id', $this->chatter->external_id)
            ->exists())->toBeTrue();
    Notification::assertSentTo($chatter, ChatterBlockedNotification::class, function (ChatterBlockedNotification $notification) use ($chatter): bool {
        $mail = $notification->toMail($chatter);

        return $notification->workspaceName === $this->agent->workspace->name
            && ! Str::contains(implode(' ', $mail->introLines), 'Prompt injection, 12 times');
    });
});

it('sends no email when the admin chose not to', function (): void {
    bcaBlock(ChatterBlockNotice::Silent);

    expect($this->chatter->fresh()->isDeactivated())->toBeTrue();
    Notification::assertNothingSent();
});
