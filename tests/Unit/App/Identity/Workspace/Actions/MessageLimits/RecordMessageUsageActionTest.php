<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\MessageLimits\RecordMessageUsageAction;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use App\Identity\Workspace\Services\MessageAllowance;
use Illuminate\Support\Carbon;
use Tests\Support\Identity\SharedId;

/**
 * Each counted message adds one to this month's count of the limit it is
 * given (a workspace's or a chatter's). A new month restarts the count on its
 * first message, and the message that reaches the limit drops that account's
 * cached "may send" answer so its next turn is refused.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
    $this->workspace = User::factory()->create()->workspace();
    $this->allowance = app(MessageAllowance::class);
    $this->action = app(RecordMessageUsageAction::class);
});

it('counts a message on the stored row, even through a stale copy, and does nothing without a limit', function (): void {
    $unlimited = User::factory()->create()->workspace();
    $limit = MessageLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 10, 'messages_used' => 3]);
    $stale = MessageLimit::query()->findOrFail($limit->id);

    $this->action->execute($this->allowance->limitFor($unlimited->id));
    $this->action->execute($limit);
    $this->action->execute($stale);

    expect($limit->fresh()->messages_used)->toBe(5)
        ->and($stale->messages_used)->toBe(5)
        ->and(MessageLimit::query()->where('workspace_id', $unlimited->id)->exists())->toBeFalse();
});

it('restarts the count once on the first messages of a new month', function (): void {
    $limit = MessageLimit::factory()->forWorkspace($this->workspace)->lastMonth()->create(['monthly_limit' => 10, 'messages_used' => 9]);
    $other = MessageLimit::query()->findOrFail($limit->id);

    $this->action->execute($limit);
    $this->action->execute($other);

    expect($limit->fresh()->messages_used)->toBe(2)
        ->and($limit->fresh()->period_starts_at->toDateTimeString())->toBe('2026-06-01 00:00:00');
});

it('refuses the next turn of only the account whose message reached its limit', function (): void {
    $workspace = Workspace::factory()->create(['id' => SharedId::workspaceAndChatter()]);
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create(['id' => $workspace->id]);
    $workspaceLimit = MessageLimit::factory()->forWorkspace($workspace)->create(['monthly_limit' => 2, 'messages_used' => 1]);
    $chatterLimit = MessageLimit::factory()->forChatter($chatter)->create(['monthly_limit' => 2, 'messages_used' => 1]);
    $warm = [$this->allowance->canSend($workspace->id), $this->allowance->canSendAsChatter($chatter->id)];

    $this->action->execute($this->allowance->limitForChatter($chatter->id));
    $workspaceAfterChatter = $this->allowance->canSend($workspace->id);
    $this->action->execute($workspaceLimit);

    expect($warm)->toBe([true, true])
        ->and($chatterLimit->fresh()->messages_used)->toBe(2)
        ->and($this->allowance->canSendAsChatter($chatter->id))->toBeFalse()
        ->and($workspaceAfterChatter)->toBeTrue()
        ->and($workspaceLimit->fresh()->messages_used)->toBe(2)
        ->and($this->allowance->canSend($workspace->id))->toBeFalse();
});
