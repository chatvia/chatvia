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

it('creates a limit that starts this month at zero', function (): void {
    $this->action->execute($this->workspace, 300);

    $limit = MessageLimit::query()->where('workspace_id', $this->workspace->id)->sole();

    expect($limit->monthly_limit)->toBe(300)
        ->and($limit->messages_used)->toBe(0)
        ->and($limit->period_starts_at->toDateTimeString())->toBe('2026-06-01 00:00:00');
});

it('keeps what was used this month when the limit changes', function (): void {
    MessageLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 300, 'messages_used' => 120]);

    $this->action->execute($this->workspace, 100);

    $limit = MessageLimit::query()->where('workspace_id', $this->workspace->id)->sole();

    expect($limit->monthly_limit)->toBe(100)
        ->and($limit->messages_used)->toBe(120)
        ->and($limit->reached())->toBeTrue();
});

it('restarts the count when the stored month is over', function (): void {
    MessageLimit::factory()->forWorkspace($this->workspace)->lastMonth()->create(['monthly_limit' => 300, 'messages_used' => 280]);

    $this->action->execute($this->workspace, 500);

    $limit = MessageLimit::query()->where('workspace_id', $this->workspace->id)->sole();

    expect($limit->messages_used)->toBe(0)
        ->and($limit->period_starts_at->toDateTimeString())->toBe('2026-06-01 00:00:00');
});

it('removes the limit when null is given, keeping this month\'s count', function (): void {
    MessageLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 300, 'messages_used' => 50]);

    $this->action->execute($this->workspace, null);

    // The row stays to count replies against the free-use cap.
    $limit = MessageLimit::query()->where('workspace_id', $this->workspace->id)->sole();

    expect($limit->monthly_limit)->toBeNull()
        ->and($limit->messages_used)->toBe(50);
});

it('applies a lower limit to the cached answer right away', function (): void {
    MessageLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 300, 'messages_used' => 50]);
    $allowance = app(MessageAllowance::class);

    expect($allowance->canSend($this->workspace->id))->toBeTrue();

    $this->action->execute($this->workspace, 50);

    expect($allowance->canSend($this->workspace->id))->toBeFalse();
});

it('sets a chatter limit on the chatter, not on a workspace', function (): void {
    $chatter = Chatter::factory()->ofWorkspace($this->workspace)->create();

    $this->action->execute($chatter, 40);

    $limit = MessageLimit::query()->where('chatter_id', $chatter->id)->sole();

    expect($limit->workspace_id)->toBeNull()
        ->and($limit->monthly_limit)->toBe(40)
        ->and($limit->chatter->is($chatter))->toBeTrue()
        ->and(MessageLimit::query()->where('workspace_id', $this->workspace->id)->exists())->toBeFalse();
});

it('keeps a workspace limit and a chatter limit with the same id apart', function (): void {
    $workspace = Workspace::factory()->create(['id' => SharedId::workspaceAndChatter()]);
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create(['id' => $workspace->id]);

    $this->action->execute($workspace, 1000);
    $this->action->execute($chatter, 20);

    expect(MessageLimit::query()->where('workspace_id', $workspace->id)->value('monthly_limit'))->toBe(1000)
        ->and(MessageLimit::query()->where('chatter_id', $chatter->id)->value('monthly_limit'))->toBe(20);

    // Removing the chatter's limit leaves the workspace's row alone.
    $this->action->execute($chatter, null);

    expect(MessageLimit::query()->where('chatter_id', $chatter->id)->exists())->toBeFalse()
        ->and(MessageLimit::query()->where('workspace_id', $workspace->id)->value('monthly_limit'))->toBe(1000);
});

it('applies a lower chatter limit to the chatter right away', function (): void {
    $chatter = Chatter::factory()->ofWorkspace($this->workspace)->create();
    MessageLimit::factory()->forChatter($chatter)->create(['monthly_limit' => 30, 'messages_used' => 10]);
    $allowance = app(MessageAllowance::class);

    expect($allowance->canSendAsChatter($chatter->id))->toBeTrue();

    $this->action->execute($chatter, 10);

    expect($allowance->canSendAsChatter($chatter->id))->toBeFalse();
});
