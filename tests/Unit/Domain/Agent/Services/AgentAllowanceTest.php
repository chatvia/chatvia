<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Account\CreateAgentAction;
use Domain\Agent\Data\Account\CreateAgentData;
use Domain\Agent\Exceptions\AgentLimitReachedException;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentAllowance;
use Support\License\FreeTier;

/**
 * Without a license key a workspace holds ten agents (a free-use limit);
 * deleted agents do not count. Any valid key lifts the limit, and agents made
 * with a key keep working after it is removed.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
});

/** Fills the owner's workspace up to the free agent limit. */
function aaFillToLimit(User $owner): void
{
    foreach (range(1, FreeTier::AGENTS) as $n) {
        createAgent($owner, ['name' => "Bot {$n}"]);
    }
}

function aaCreate(User $owner): Agent
{
    return app(CreateAgentAction::class)->execute(new CreateAgentData(user: $owner, team: ownerTeam($owner), name: 'One more'));
}

it('refuses the eleventh agent without a license key', function (): void {
    aaFillToLimit($this->owner);

    expect(app(AgentAllowance::class)->canCreate($this->owner->workspace()))->toBeFalse()
        ->and(fn () => aaCreate($this->owner))->toThrow(AgentLimitReachedException::class);

    expect(app(AgentAllowance::class)->count($this->owner->workspace()))->toBe(FreeTier::AGENTS);
});

it('does not count deleted agents', function (): void {
    aaFillToLimit($this->owner);
    Agent::query()->where('workspace_id', $this->owner->workspace()->id)->first()->delete();

    expect(aaCreate($this->owner)->exists)->toBeTrue();
});

it('allows more agents with a license key', function (): void {
    withLicense();
    aaFillToLimit($this->owner);

    expect(aaCreate($this->owner)->exists)->toBeTrue();
});

it('keeps agents made with a key after it is removed, but refuses new ones', function (): void {
    withLicense();
    aaFillToLimit($this->owner);
    $extra = aaCreate($this->owner);

    withoutLicense();

    expect(Agent::query()->whereKey($extra->id)->exists())->toBeTrue()
        ->and(fn () => aaCreate($this->owner))->toThrow(AgentLimitReachedException::class);
});
