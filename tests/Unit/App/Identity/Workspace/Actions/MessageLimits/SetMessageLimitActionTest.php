<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\MessageLimits\SetMessageLimitAction;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use App\Identity\Workspace\Services\MessageAllowance;
use Illuminate\Support\Carbon;
use Tests\Support\Identity\SharedId;

/**
 * Setting a limit creates or updates the account's one row (a workspace's on
 * `workspace_id`, a chatter's on `chatter_id`) and keeps this month's count; null
 * removes the row (no limit). Every change drops that account's cached "may
 * send" answer, so it applies on the next turn.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
    $this->workspace = User::factory()->create()->workspace();
    $this->action = app(SetMessageLimitAction::class);
});

it('creates a limit at zero, keeps this month\'s count on a change or removal, and restarts an over month', function (): void {
    $changed = User::factory()->create()->workspace();
    $over = User::factory()->create()->workspace();
    MessageLimit::factory()->forWorkspace($changed)->create(['monthly_limit' => 300, 'messages_used' => 120]);
    MessageLimit::factory()->forWorkspace($over)->lastMonth()->create(['monthly_limit' => 300, 'messages_used' => 280]);

    $this->action->execute($this->workspace, 300);
    $this->action->execute($changed, 100);
    $lowered = MessageLimit::query()->where('workspace_id', $changed->id)->sole();
    $this->action->execute($changed, null);
    $this->action->execute($over, 500);

    $created = MessageLimit::query()->where('workspace_id', $this->workspace->id)->sole();
    $removed = MessageLimit::query()->where('workspace_id', $changed->id)->sole();
    $restarted = MessageLimit::query()->where('workspace_id', $over->id)->sole();
    expect($created)->monthly_limit->toBe(300)->messages_used->toBe(0)
        ->and($created->period_starts_at->toDateTimeString())->toBe('2026-06-01 00:00:00')
        ->and($lowered)->monthly_limit->toBe(100)->messages_used->toBe(120)
        ->and($lowered->reached())->toBeTrue()
        ->and($removed)->monthly_limit->toBeNull()->messages_used->toBe(120)
        ->and($restarted->messages_used)->toBe(0)
        ->and($restarted->period_starts_at->toDateTimeString())->toBe('2026-06-01 00:00:00');
});

it('applies a lower workspace or chatter limit to the cached answer right away', function (): void {
    $chatter = Chatter::factory()->ofWorkspace($this->workspace)->create();
    MessageLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 300, 'messages_used' => 50]);
    MessageLimit::factory()->forChatter($chatter)->create(['monthly_limit' => 30, 'messages_used' => 10]);
    $allowance = app(MessageAllowance::class);
    $warm = [$allowance->canSend($this->workspace->id), $allowance->canSendAsChatter($chatter->id)];

    $this->action->execute($this->workspace, 50);
    $this->action->execute($chatter, 10);

    expect($warm)->toBe([true, true])
        ->and($allowance->canSend($this->workspace->id))->toBeFalse()
        ->and($allowance->canSendAsChatter($chatter->id))->toBeFalse();
});

it('keeps a chatter limit on the chatter, apart from a workspace limit with the same id', function (): void {
    $workspace = Workspace::factory()->create(['id' => SharedId::workspaceAndChatter()]);
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create(['id' => $workspace->id]);

    $this->action->execute($workspace, 1000);
    $this->action->execute($chatter, 20);
    $chatterLimit = MessageLimit::query()->where('chatter_id', $chatter->id)->sole();
    $this->action->execute($chatter, null);

    expect($chatterLimit->workspace_id)->toBeNull()
        ->and($chatterLimit->monthly_limit)->toBe(20)
        ->and($chatterLimit->chatter->is($chatter))->toBeTrue()
        ->and(MessageLimit::query()->where('chatter_id', $chatter->id)->exists())->toBeFalse()
        ->and(MessageLimit::query()->where('workspace_id', $workspace->id)->value('monthly_limit'))->toBe(1000);
});
