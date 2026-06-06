<?php

namespace Domain\Integration\Function\Services;

use Domain\Integration\Function\Enums\FunctionAuthType;

/**
 * Flattens (auth_type, auth_value, auth_header_name) input into the typed
 * shape the AgentFunction model stores under `auth_config`. Centralised here
 * so adding a new auth kind (oauth2, mtls, …) is a single-file edit instead
 * of touching three Actions and four FormRequests in lockstep.
 */
class AuthConfigBuilder
{
    /**
     * @return array<string, string>|null
     */
    public function build(string|FunctionAuthType $type, ?string $value, ?string $headerName = null): ?array
    {
        $type = $type instanceof FunctionAuthType ? $type : FunctionAuthType::tryFrom($type);
        $value = (string) ($value ?? '');

        return match ($type) {
            FunctionAuthType::Bearer => ['token' => $value],
            FunctionAuthType::Header => ['name' => (string) ($headerName ?? ''), 'value' => $value],
            FunctionAuthType::Hmac => ['secret' => $value],
            default => null,
        };
    }

    /**
     * Merge submitted credentials against the stored set: when the caller
     * leaves a field blank we carry the existing secret forward (so editing
     * the URL doesn't force the user to re-enter tokens they can no longer
     * read). Returning null clears the column.
     *
     * @param  array<string, string>|null  $existing
     * @return array<string, string>|null
     */
    public function merge(
        string|FunctionAuthType $newType,
        string|FunctionAuthType|null $existingType,
        ?array $existing,
        ?string $value,
        ?string $headerName = null,
    ): ?array {
        $newType = $newType instanceof FunctionAuthType ? $newType : FunctionAuthType::tryFrom($newType);
        $existingType = $existingType instanceof FunctionAuthType
            ? $existingType
            : ($existingType !== null ? FunctionAuthType::tryFrom($existingType) : null);

        if ($newType === FunctionAuthType::None) {
            return null;
        }

        // Only carry the stored secret forward when the auth type is unchanged.
        $carry = ($newType === $existingType ? ($existing ?? []) : []);

        return match ($newType) {
            FunctionAuthType::Bearer => ['token' => $value ?? ($carry['token'] ?? '')],
            FunctionAuthType::Header => [
                'name' => ($headerName !== null && $headerName !== '') ? $headerName : ($carry['name'] ?? ''),
                'value' => $value ?? ($carry['value'] ?? ''),
            ],
            FunctionAuthType::Hmac => ['secret' => $value ?? ($carry['secret'] ?? '')],
            default => null,
        };
    }
}
