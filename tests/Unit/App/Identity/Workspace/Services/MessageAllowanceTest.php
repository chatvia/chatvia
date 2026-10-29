<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use App\Identity\Workspace\Services\MessageAllowance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Identity\SharedId;

/**
 * MessageAllowance answers "may this account send one more message this
 * month?" from its MessageLimit, cached per account and month. A workspace
 * (workspaces.id) and a chatter (chatters.id) are separate accounts: the same
 * integer id never makes one read the other's limit or cached answer.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
    $this->workspace = User::factory()->create()->workspace();
    $this->allowance = app(MessageAllowance::class);
});

/**
 * A workspace and one of its chatters with the same integer id, free in both
 * tables.
 *
 * @return array{0: Workspace, 1: Chatter}
 */
function allowanceWorkspaceAndChatterSharingAnId(): array
{
    $workspace = Workspace::factory()->create(['id' => SharedId::workspaceAndChatter()]);

    return [$workspace, Chatter::factory()->ofWorkspace($workspace)->create(['id' => $workspace->id])];
}

it('lets a workspace or chatter without a cap send, and refuses one at its limit until a new month', function (): void {
    $chatter = Chatter::factory()->ofWorkspace($this->workspace)->create();
    $capped = Chatter::factory()->ofWorkspace($this->workspace)->create();
    $nullCap = User::factory()->create()->workspace();
    $reached = User::factory()->create()->workspace();
    MessageLimit::factory()->forChatter($capped)->reached()->create();
    MessageLimit::factory()->forWorkspace($nullCap)->create(['monthly_limit' => null, 'messages_used' => 1_000_000]);
    MessageLimit::factory()->forWorkspace($reached)->reached()->create();

    $june = [
        $this->allowance->canSend($this->workspace->id),
        $this->allowance->canSendAsChatter($chatter->id),
        $this->allowance->canSend($nullCap->id),
        $this->allowance->canSendAsChatter($capped->id),
        $this->allowance->canSend($reached->id),
    ];
    $this->travelTo(Carbon::parse('2026-07-01 00:05:00'));

    expect($june)->toBe([true, true, true, false, false])
        ->and($this->allowance->limitForChatter($capped->id)?->chatter_id)->toBe($capped->id)
        ->and($this->allowance->canSend($reached->id))->toBeTrue();
});

it('never refuses a workspace over a chatter limit with the same id, or the other way round', function (): void {
    [$workspace, $chatter] = allowanceWorkspaceAndChatterSharingAnId();
    [$otherWorkspace, $otherChatter] = allowanceWorkspaceAndChatterSharingAnId();
    MessageLimit::factory()->forChatter($chatter)->reached()->create();
    MessageLimit::factory()->forWorkspace($otherWorkspace)->reached()->create();

    expect($this->allowance->canSendAsChatter($chatter->id))->toBeFalse()
        ->and($this->allowance->canSend($workspace->id))->toBeTrue()
        ->and($this->allowance->limitFor($workspace->id))->toBeNull()
        ->and($this->allowance->canSend($otherWorkspace->id))->toBeFalse()
        ->and($this->allowance->canSendAsChatter($otherChatter->id))->toBeTrue()
        ->and($this->allowance->limitForChatter($otherChatter->id))->toBeNull();
});

it('answers from a cache keyed apart for a workspace and a chatter with the same id', function (): void {
    [$workspace, $chatter] = allowanceWorkspaceAndChatterSharingAnId();
    MessageLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 5, 'messages_used' => 1]);
    $this->allowance->canSend($this->workspace->id);
    DB::enableQueryLog();
    $this->allowance->canSend($this->workspace->id);
    $warmQueries = collect(DB::getQueryLog())->filter(fn (array $q): bool => Str::contains($q['query'], 'message_limits'));
    $workspaceWarm = $this->allowance->canSend($workspace->id);
    MessageLimit::factory()->forChatter($chatter)->reached()->create();
    $chatterAnswer = $this->allowance->canSendAsChatter($chatter->id);
    MessageLimit::factory()->forWorkspace($workspace)->reached()->create();

    $this->allowance->forgetChatter($chatter->id);
    $afterForgettingChatter = $this->allowance->canSend($workspace->id);
    $this->allowance->forget($workspace->id);

    expect($warmQueries)->toBeEmpty()
        ->and($workspaceWarm)->toBeTrue()
        ->and($chatterAnswer)->toBeFalse()
        ->and($afterForgettingChatter)->toBeTrue()
        ->and($this->allowance->canSend($workspace->id))->toBeFalse();
});
