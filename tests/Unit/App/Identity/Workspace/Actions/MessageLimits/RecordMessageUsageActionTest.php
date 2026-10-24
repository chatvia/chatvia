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

it('does nothing for an account without a limit', function (): void {
    $this->action->execute($this->allowance->limitFor($this->workspace->id));

    expect(MessageLimit::query()->where('workspace_id', $this->workspace->id)->exists())->toBeFalse();
});

it('counts a message in the current month', function (): void {
    $limit = MessageLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 10, 'messages_used' => 3]);

    $this->action->execute($limit);

    expect($limit->fresh()->messages_used)->toBe(4);
});

it('restarts the count on the first message of a new month', function (): void {
    $limit = MessageLimit::factory()->forWorkspace($this->workspace)->lastMonth()->reached()->create();

    $this->action->execute($limit);

    $limit = MessageLimit::query()->where('workspace_id', $this->workspace->id)->sole();

    expect($limit->messages_used)->toBe(1)
        ->and($limit->period_starts_at->toDateTimeString())->toBe('2026-06-01 00:00:00');
});

it('refuses the next turn once the message reaching the limit is counted', function (): void {
    $limit = MessageLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 2, 'messages_used' => 1]);

    // Warm the cached answer while there is still room.
    expect($this->allowance->canSend($this->workspace->id))->toBeTrue();

    $this->action->execute($limit);

    expect($this->allowance->canSend($this->workspace->id))->toBeFalse();
});

it('refuses only the chatter when their message reaches their limit', function (): void {
    // Same integer id as the workspace, so a mixed-up row or cache key shows.
    $workspace = Workspace::factory()->create(['id' => SharedId::workspaceAndChatter()]);
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create(['id' => $workspace->id]);

    $workspaceLimit = MessageLimit::factory()->forWorkspace($workspace)->create(['monthly_limit' => 2, 'messages_used' => 1]);
    $chatterLimit = MessageLimit::factory()->forChatter($chatter)->create(['monthly_limit' => 2, 'messages_used' => 1]);

    expect($this->allowance->canSend($workspace->id))->toBeTrue()
        ->and($this->allowance->canSendAsChatter($chatter->id))->toBeTrue();

    $this->action->execute($this->allowance->limitForChatter($chatter->id));

    expect($chatterLimit->fresh()->messages_used)->toBe(2)
        ->and($workspaceLimit->fresh()->messages_used)->toBe(1)
        ->and($this->allowance->canSendAsChatter($chatter->id))->toBeFalse()
        ->and($this->allowance->canSend($workspace->id))->toBeTrue();
});

it('counts from the stored row, not from a stale copy of the limit', function (): void {
    $limit = MessageLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 10, 'messages_used' => 3]);
    $stale = MessageLimit::query()->findOrFail($limit->id);

    // Another reply counted meanwhile; the stale copy still says 3.
    $this->action->execute($limit);
    $this->action->execute($stale);

    expect($limit->fresh()->messages_used)->toBe(5)
        ->and($stale->messages_used)->toBe(5);
});

it('restarts the month only once when two copies of last month\'s row count', function (): void {
    $limit = MessageLimit::factory()->forWorkspace($this->workspace)->lastMonth()->create(['monthly_limit' => 10, 'messages_used' => 9]);
    $other = MessageLimit::query()->findOrFail($limit->id);

    $this->action->execute($limit);
    $this->action->execute($other);

    expect($limit->fresh()->messages_used)->toBe(2)
        ->and($limit->fresh()->period_starts_at->toDateTimeString())->toBe('2026-06-01 00:00:00');
});
