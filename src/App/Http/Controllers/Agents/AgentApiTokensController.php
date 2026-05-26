<?php

namespace App\Http\Controllers\Agents;

use App\Http\Controllers\Controller;
use Domain\Agents\Actions\CreateAgentApiTokenAction;
use Domain\Agents\Actions\DeleteAgentApiTokenAction;
use Domain\Agents\Data\Resources\AgentResourceData;
use Domain\Agents\Models\Agent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentApiTokensController extends Controller
{
    public const ABILITIES = [
        'agent:read',
        'agent:write',
        'chat:send',
        'sources:write',
        'mcps:write',
        'functions:write',
    ];

    public function index(Request $request, Agent $agent): Response
    {
        $tokens = $agent->tokens()
            ->latest()
            ->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at'])
            ->map(fn ($t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'abilities' => $t->abilities,
                'last_used_at' => $t->last_used_at?->diffForHumans(),
                'last_used_iso' => $t->last_used_at?->toIso8601String(),
                'expires_at' => $t->expires_at?->toFormattedDateString(),
                'created_at' => $t->created_at?->toFormattedDateString(),
            ])
            ->all();

        return Inertia::render('agents/ApiTokens', [
            'agent' => AgentResourceData::fromModel($agent),
            'tokens' => $tokens,
            'available_abilities' => self::ABILITIES,
            'created_token' => $request->session()->get('created_token'),
        ]);
    }

    public function store(Request $request, Agent $agent, CreateAgentApiTokenAction $createToken): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:64'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', 'in:'.implode(',', self::ABILITIES)],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:730'],
        ]);

        $token = $createToken->execute(
            agent: $agent,
            name: $validated['name'],
            abilities: $validated['abilities'],
            expiresInDays: $validated['expires_in_days'] ?? null,
        );

        return back()->with('created_token', $token);
    }

    public function destroy(Request $request, Agent $agent, int $tokenId, DeleteAgentApiTokenAction $delete): RedirectResponse
    {
        $delete->execute($agent, $tokenId);

        return back();
    }
}
