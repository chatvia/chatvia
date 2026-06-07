<?php

namespace App\Http\Controllers\Chat\Auth;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ChatterAgentController
{
    public function __invoke(Request $request): Collection
    {
        /** @var User $user */
        $user = $request->user();

        return $user->listAccessibleAgents()
            ->map(fn (Agent $agent): array => $agent->only(['id', 'external_id', 'name', 'sub_domain']))
            ->values();
    }
}
