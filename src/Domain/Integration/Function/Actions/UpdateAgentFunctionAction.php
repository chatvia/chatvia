<?php

namespace Domain\Integration\Function\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Data\UpdateAgentFunctionData;
use Domain\Integration\Function\Models\AgentFunction;

class UpdateAgentFunctionAction
{
    public function execute(User $user, string $externalId, UpdateAgentFunctionData $data): AgentFunction
    {
        /** @var AgentFunction $function */
        $function = $user->functions()->where('external_id', $externalId)->firstOrFail();

        $attributes = [
            'internal_name' => $data->internal_name,
            'display_name' => $data->display_name,
            'description' => $data->description,
            'parameters_schema' => $data->parameters_schema,
            'webhook_url' => $data->webhook_url,
            'webhook_method' => $data->webhook_method,
            'auth_type' => $data->auth_type,
            'auth_config' => $this->resolveAuthConfig($function, $data),
        ];

        // Only touch the approval flag when the caller sent one, so an API
        // update that omits it never resets the user's choice.
        if ($data->requires_approval !== null) {
            $attributes['requires_approval'] = $data->requires_approval;
        }

        $function->update($attributes);

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
