<?php

namespace App\Identity\Chatter\Data;

use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Collection;
use Spatie\LaravelData\Data;

/**
 * Inputs for {@see CreateChatterAction}: the workspace owner, the agent (or
 * agents, all owned by that workspace) the chatter may use, and the login.
 * Without an email the chatter is token-only (a random password, no login
 * form).
 */
class CreateChatterData extends Data
{
    /**
     * @param  Agent|Collection<int, Agent>  $agents
     */
    public function __construct(
        public User $owner,
        public Agent|Collection $agents,
        public string $name,
        public ?string $email = null,
        public ?string $password = null,
    ) {}

    /**
     * @return list<int>
     */
    public function agentIds(): array
    {
        return $this->agents instanceof Agent
            ? [$this->agents->id]
            : array_values($this->agents->modelKeys());
    }
}
