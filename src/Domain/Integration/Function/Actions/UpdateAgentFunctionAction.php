<?php

namespace Domain\Integration\Function\Actions;

use Domain\Integration\Function\Actions\Concerns\GuardsFunctionDefinition;
use Domain\Integration\Function\Data\FunctionDefinitionData;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Function\Services\AuthConfigBuilder;
use Spatie\LaravelData\Optional;

/**
 * Update a workspace webhook function — from the dashboard form or the public
 * API. Only the fields the caller sent change. A rename is checked against
 * the workspace's other functions; a blank credential keeps the stored one
 * when the auth type is unchanged; switching to a write verb turns the
 * approval gate on unless the caller set it.
 */
class UpdateAgentFunctionAction
{
    use GuardsFunctionDefinition;

    /** Fields copied as sent; credentials and approval have their own rules. */
    private const array PLAIN_FIELDS = [
        'internal_name',
        'display_name',
        'description',
        'parameters_schema',
        'webhook_url',
        'webhook_method',
        'webhook_headers',
        'timeout_ms',
        'is_enabled',
    ];

    public function __construct(private readonly AuthConfigBuilder $authConfig) {}

    public function execute(AgentFunction $function, FunctionDefinitionData $data): AgentFunction
    {
        $attributes = [];
        foreach (self::PLAIN_FIELDS as $field) {
            if (! $data->{$field} instanceof Optional) {
                $attributes[$field] = $data->{$field};
            }
        }

        if (! $data->internal_name instanceof Optional) {
            $this->ensureNameAvailable($function->user_id, $data->internal_name, $function->id);
        }

        if (! $data->auth_type instanceof Optional) {
            $attributes['auth_type'] = $data->auth_type;
            $attributes['auth_config'] = $this->authConfig->merge(
                newType: $data->auth_type,
                existingType: $function->auth_type,
                existing: $function->auth_config,
                value: $data->authValue(),
                headerName: $data->authHeaderName(),
            );
        }

        $requiresApproval = $this->requiresApproval($function, $data);
        if ($requiresApproval !== null) {
            $attributes['requires_approval'] = $requiresApproval;
        }

        $this->guardUniqueName(fn (): bool => $function->update($attributes));

        return $function->refresh();
    }

    /**
     * The approval flag only changes when the caller sent one — an update
     * that omits it never resets the workspace's choice — or when a switch
     * to a write verb has to turn it on.
     */
    private function requiresApproval(AgentFunction $function, FunctionDefinitionData $data): ?bool
    {
        if (! $data->requires_approval instanceof Optional) {
            return $data->requires_approval;
        }

        return $data->webhook_method instanceof Optional
            ? null
            : $this->approvalAfterMethodChange($function, $data->webhook_method);
    }
}
