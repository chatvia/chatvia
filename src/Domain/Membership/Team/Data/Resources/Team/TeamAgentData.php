<?php

namespace Domain\Membership\Team\Data\Resources\Team;

use Domain\Agent\Models\Agent;
use Domain\Membership\Team\Models\Team;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An agent of a team, as the team page and role editor list it.
 */
#[TypeScript]
class TeamAgentData extends Data
{
    public function __construct(
        public int $id,
        public string $external_id,
        public string $name,
    ) {}

    public static function fromModel(Agent $agent): self
    {
        return new self(
            id: $agent->id,
            external_id: $agent->external_id,
            name: $agent->name,
        );
    }

    /**
     * @return Collection<int, self>
     */
    public static function collectForTeam(Team $team): Collection
    {
        return $team->agents()
            ->get(['id', 'external_id', 'name'])
            ->map(fn (Agent $agent): self => self::fromModel($agent));
    }
}
