<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Http\Rules\WorkspaceRule;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentAccessPolicy;
use Illuminate\Validation\Rules\Exists;

/**
 * The rule for an agent id a person hands to a chatter: one of the
 * workspace's agents that the person may manage. A teammate is limited to the
 * agents their team role reaches, as on the dashboard; the owner reaches all.
 */
trait ValidatesManageableAgents
{
    protected function manageableAgentExists(): Exists
    {
        /** @var User $user */
        $user = $this->user();

        $rule = WorkspaceRule::exists('agents', $user->workspace(), 'external_id')->withoutTrashed();

        if ($user->isWorkspaceOwner()) {
            return $rule;
        }

        return $rule->whereIn('id', app(AgentAccessPolicy::class)->manageableAgents($user)->pluck('id')->all());
    }
}
