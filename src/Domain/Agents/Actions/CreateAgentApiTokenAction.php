<?php

namespace Domain\Agents\Actions;

use Domain\Agents\Models\Agent;
use Illuminate\Support\Carbon;

class CreateAgentApiTokenAction
{
    /**
     * @param  array<int, string>  $abilities
     * @return array{id: int, name: string, plaintext: string, abilities: array<int, string>}
     */
    public function execute(Agent $agent, string $name, array $abilities, ?int $expiresInDays): array
    {
        $expiresAt = $expiresInDays !== null ? Carbon::now()->addDays($expiresInDays) : null;

        $token = $agent->createToken($name, $abilities, $expiresAt);

        return [
            'id' => $token->accessToken->id,
            'name' => $token->accessToken->name,
            'plaintext' => $token->plainTextToken,
            'abilities' => $token->accessToken->abilities,
        ];
    }
}
