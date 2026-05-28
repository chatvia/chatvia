<?php

namespace Domain\Agents\Actions;

use App\Auth\Models\User;
use Domain\Agents\Data\UpdateAgentFunctionData;
use Domain\Agents\Models\AgentFunction;

class UpdateAgentFunctionAction
{
    public function execute(User $user, string $externalId, UpdateAgentFunctionData $data): AgentFunction
    {
        /** @var AgentFunction $function */
        $function = $user->functions()->where('external_id', $externalId)->firstOrFail();

        $function->update([
            'name' => $data->name,
            'description' => $data->description,
            'parameters_schema' => $data->parameters_schema,
            'webhook_url' => $data->webhook_url,
            'webhook_method' => $data->webhook_method,
            'auth_type' => $data->auth_type,
            'auth_config' => $this->resolveAuthConfig($function, $data),
        ]);

        return $function;
    }

    /**
     * Merge the submitted credentials against what's stored. A blank
     * `auth_value` keeps the existing secret, so editing unrelated fields
     * doesn't force the user to re-enter tokens they can no longer read.
     *
     * @return array<string, string>|null
     */
    private function resolveAuthConfig(AgentFunction $function, UpdateAgentFunctionData $data): ?array
    {
        if ($data->auth_type === 'none') {
            return null;
        }

        // Only carry the stored secret forward when the auth type is unchanged.
        $existing = $function->auth_type === $data->auth_type ? ($function->auth_config ?? []) : [];

        return match ($data->auth_type) {
            'bearer' => ['token' => $data->auth_value ?? ($existing['token'] ?? '')],
            'header' => [
                'name' => $data->auth_header_name ?: ($existing['name'] ?? ''),
                'value' => $data->auth_value ?? ($existing['value'] ?? ''),
            ],
            'hmac' => ['secret' => $data->auth_value ?? ($existing['secret'] ?? '')],
            default => null,
        };
    }
}
