<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use App\Identity\Workspace\Services\MessageAllowance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Support\License\FreeTier;
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

it('lets an account without a limit send', function (): void {
    expect($this->allowance->canSend($this->workspace->id))->toBeTrue();
});

it('refuses an account that used its whole limit this month', function (): void {
    MessageLimit::factory()->forWorkspace($this->workspace)->reached()->create();

    expect($this->allowance->canSend($this->workspace->id))->toBeFalse();
});

it('lets the account send again when a new month starts', function (): void {
    MessageLimit::factory()->forWorkspace($this->workspace)->reached()->create();

    expect($this->allowance->canSend($this->workspace->id))->toBeFalse();

    // The cached answer belongs to June, so July reads the row again.
    $this->travelTo(Carbon::parse('2026-07-01 00:05:00'));

    expect($this->allowance->canSend($this->workspace->id))->toBeTrue();
});

it('answers from the cache without a query once warmed', function (): void {
    MessageLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 5, 'messages_used' => 1]);
    $this->allowance->canSend($this->workspace->id);

    DB::enableQueryLog();
    $this->allowance->canSend($this->workspace->id);

    expect(collect(DB::getQueryLog())->filter(fn (array $q): bool => Str::contains($q['query'], 'message_limits')))->toBeEmpty();
});

it('lets a chatter without a limit send', function (): void {
    $chatter = Chatter::factory()->ofWorkspace($this->workspace)->create();

    expect($this->allowance->canSendAsChatter($chatter->id))->toBeTrue();
});

it('refuses a chatter who used their whole limit this month', function (): void {
    $chatter = Chatter::factory()->ofWorkspace($this->workspace)->create();
    MessageLimit::factory()->forChatter($chatter)->reached()->create();

    expect($this->allowance->canSendAsChatter($chatter->id))->toBeFalse()
        ->and($this->allowance->limitForChatter($chatter->id)?->chatter_id)->toBe($chatter->id);
});

it('does not refuse a workspace over a chatter limit with the same id', function (): void {
    [$workspace, $chatter] = allowanceWorkspaceAndChatterSharingAnId();
    MessageLimit::factory()->forChatter($chatter)->reached()->create();

    expect($this->allowance->canSendAsChatter($chatter->id))->toBeFalse()
        ->and($this->allowance->canSend($workspace->id))->toBeTrue()
        ->and($this->allowance->limitFor($workspace->id))->toBeNull();
});

it('does not refuse a chatter over a workspace limit with the same id', function (): void {
    [$workspace, $chatter] = allowanceWorkspaceAndChatterSharingAnId();
    MessageLimit::factory()->forWorkspace($workspace)->reached()->create();

    expect($this->allowance->canSend($workspace->id))->toBeFalse()
        ->and($this->allowance->canSendAsChatter($chatter->id))->toBeTrue()
        ->and($this->allowance->limitForChatter($chatter->id))->toBeNull();
});

it('caches a workspace and a chatter with the same id under separate keys', function (): void {
    [$workspace, $chatter] = allowanceWorkspaceAndChatterSharingAnId();

    // Warm the workspace's "may send" answer, then reach the chatter's limit:
    // a shared cache key would answer the chatter from the workspace's entry.
    expect($this->allowance->canSend($workspace->id))->toBeTrue();
    MessageLimit::factory()->forChatter($chatter)->reached()->create();

    expect($this->allowance->canSendAsChatter($chatter->id))->toBeFalse();

    // Forgetting the chatter's answer leaves the workspace's cached answer.
    MessageLimit::factory()->forWorkspace($workspace)->reached()->create();
    $this->allowance->forgetChatter($chatter->id);

    expect($this->allowance->canSend($workspace->id))->toBeTrue();

    $this->allowance->forget($workspace->id);

    expect($this->allowance->canSend($workspace->id))->toBeFalse();
});

describe('the free-use cap', function (): void {
    it('counts a workspace without a license key, and stops it at the cap', function (): void {
        $limit = $this->allowance->countingLimitFor($this->workspace->id);

        expect($limit)->not->toBeNull()
            ->and($limit->monthly_limit)->toBeNull()
            ->and($this->allowance->canSend($this->workspace->id))->toBeTrue();

        $limit->update(['messages_used' => FreeTier::MONTHLY_MESSAGES]);
        $this->allowance->forget($this->workspace->id);

        expect($this->allowance->canSend($this->workspace->id))->toBeFalse()
            ->and($this->allowance->effectiveLimit($limit))->toBe(FreeTier::MONTHLY_MESSAGES);
    });

    it('lets a lower admin limit win over the cap', function (): void {
        $limit = MessageLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 500]);

        expect($this->allowance->effectiveLimit($limit))->toBe(500);
    });

    it('lifts the cap with a license key, at once', function (): void {
        MessageLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => null, 'messages_used' => FreeTier::MONTHLY_MESSAGES]);

        expect($this->allowance->canSend($this->workspace->id))->toBeFalse();

        withLicense();

        expect($this->allowance->canSend($this->workspace->id))->toBeTrue()
            ->and($this->allowance->countingLimitFor(User::factory()->create()->workspace()->id))->toBeNull();
    });

    it('never caps a chatter\'s own messages', function (): void {
        $chatter = Chatter::factory()->create(['workspace_id' => $this->workspace->id]);
        $limit = MessageLimit::factory()->forChatter($chatter)->create(['monthly_limit' => 50_000, 'messages_used' => FreeTier::MONTHLY_MESSAGES]);

        expect($this->allowance->effectiveLimit($limit))->toBe(50_000)
            ->and($this->allowance->canSendAsChatter($chatter->id))->toBeTrue();
    });
});
