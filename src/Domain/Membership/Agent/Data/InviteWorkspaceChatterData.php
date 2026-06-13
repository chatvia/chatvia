<?php

namespace Domain\Membership\Agent\Data;

use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\InviteWorkspaceChatterAction;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

/**
 * Inputs for {@see InviteWorkspaceChatterAction} —
 * invite one chatter from the workspace Members page and grant them access to a
 * set of protected agents (at least one).
 *
 * `email` arrives already lowercased + trimmed; `agents` are already resolved
 * and scoped to the workspace (protected only) by the FormRequest.
 *
 * @property Collection<int, Agent> $agents
 */
class InviteWorkspaceChatterData extends Data
{
    /** @param Collection<int, Agent> $agents */
    public function __construct(
        public string $email,
        public Collection $agents,
    ) {}
}
