<?php

namespace Domain\Channel\Services;

use Closure;
use Domain\Agent\Models\Agent;
use Domain\Channel\Exceptions\ChannelConnectException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * The rules every channel connect action shares: messaging channels serve
 * public agents only (a channel sender is anonymous and can't satisfy a
 * protected agent's access model), and one bot / page / number / account
 * powers at most one agent.
 */
class ChannelConnectionGuard
{
    public function ensureAgentIsPublic(Agent $agent): void
    {
        if (! $agent->is_public) {
            throw ChannelConnectException::protectedAgent();
        }
    }

    /**
     * Refuse an account another agent already uses. Checked before any call
     * to the platform, so a refused connection costs nothing.
     *
     * @param  Builder<covariant Model>  $sameAccount  the connection rows for this account
     */
    public function ensureAccountIsFree(Agent $agent, Builder $sameAccount): void
    {
        if ($sameAccount->where('agent_id', '!=', $agent->id)->exists()) {
            throw ChannelConnectException::takenByAnotherAgent();
        }
    }

    /**
     * Run the connection write. The unique index on the account id is the
     * real guard when two agents race for the same account; the loser gets the
     * same "taken" failure as the up-front check.
     *
     * @template TModel of Model
     *
     * @param  Closure(): TModel  $write
     * @return TModel
     */
    public function store(Closure $write): Model
    {
        try {
            return $write();
        } catch (UniqueConstraintViolationException) {
            throw ChannelConnectException::takenByAnotherAgent();
        }
    }
}
