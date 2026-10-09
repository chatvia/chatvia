<?php

namespace Domain\Integration\Function\Services;

use Domain\Integration\Function\Enums\FunctionAuthType;

/**
 * Turns flat credential input (auth type, value, optional header name) into
 * the typed shape AgentFunction stores under `auth_config`:
 *
 *   bearer → ['token' => '…']
 *   header → ['name' => '…', 'value' => '…']
 *   hmac   → ['secret' => '…']
 *   none   → null
 *
 * Kept in one place so a new auth kind is a single-file change.
 */
class AuthConfigBuilder
{
    /**
     * @return array<string, string>|null
     */
    public function build(FunctionAuthType $type, ?string $value, ?string $headerName = null): ?array
    {
        $value ??= '';

        return match ($type) {
            FunctionAuthType::Bearer => ['token' => $value],
            FunctionAuthType::Header => ['name' => $headerName ?? '', 'value' => $value],
            FunctionAuthType::Hmac => ['secret' => $value],
            FunctionAuthType::None => null,
        };
    }

    /**
     * Merge submitted credentials with the stored set: a field left blank
     * keeps the stored secret (so editing the URL doesn't force the user to
     * re-enter a token they can no longer read). The stored secret is only
     * carried over when the auth type is unchanged. Returning null clears
     * the column.
     *
     * @param  array<string, string>|null  $existing
     * @return array<string, string>|null
     */
    public function merge(
        FunctionAuthType $newType,
        ?FunctionAuthType $existingType,
        ?array $existing,
        ?string $value,
        ?string $headerName = null,
    ): ?array {
        $carry = $newType === $existingType ? ($existing ?? []) : [];

        return match ($newType) {
            FunctionAuthType::Bearer => ['token' => $value ?? ($carry['token'] ?? '')],
            FunctionAuthType::Header => [
                'name' => ($headerName !== null && $headerName !== '') ? $headerName : ($carry['name'] ?? ''),
                'value' => $value ?? ($carry['value'] ?? ''),
            ],
            FunctionAuthType::Hmac => ['secret' => $value ?? ($carry['secret'] ?? '')],
            FunctionAuthType::None => null,
        };
    }
}
