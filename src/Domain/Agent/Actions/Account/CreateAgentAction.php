<?php

namespace Domain\Agent\Actions\Account;

use App\Identity\Workspace\Models\Workspace;
use Domain\Agent\Data\Account\CreateAgentData;
use Domain\Agent\Exceptions\AgentLimitReachedException;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentAllowance;
use Domain\Agent\Services\AgentLocator as AgentService;
use Domain\Conversation\Topic\Models\Topic;
use Illuminate\Support\Facades\DB;

/**
 * Creates a new agent from the dashboard. The agent always belongs to the
 * workspace owner, also when a teammate creates it, so it stays with the
 * workspace if that teammate is removed. It starts private, gets a random
 * external id (also used as its first chat slug) and its own signing secret,
 * and is linked to every topic its workspace can use (the system topics and
 * the workspace's own) so conversation tagging works from the start — never to
 * another workspace's custom topics. Without a license key a workspace holds
 * ten agents; the workspace row is locked so two creates at once cannot both
 * pass the check.
 */
class CreateAgentAction
{
    public function __construct(
        private readonly AgentAllowance $allowance,
    ) {}

    /**
     * @throws AgentLimitReachedException
     */
    public function execute(CreateAgentData $data): Agent
    {
        return DB::transaction(function () use ($data): Agent {
            $workspace = $data->user->workspace();

            Workspace::query()->whereKey($workspace->id)->lockForUpdate()->first();

            if (! $this->allowance->canCreate($workspace)) {
                throw AgentLimitReachedException::make((int) $this->allowance->limit());
            }

            $externalId = AgentService::generateRandomExternalId();

            /** @var Agent $agent */
            $agent = Agent::create([
                'workspace_id' => $workspace->id,
                'team_id' => $data->team->id,
                'name' => $data->name,
                'slug' => $externalId,
                'is_public' => false,
                'external_id' => $externalId,
                'auth_secret' => Agent::generateAuthSecret(),
            ]);

            $agent->topics()->attach(Topic::forWorkspace($workspace)->pluck('id'));

            return $agent;
        });
    }
}
