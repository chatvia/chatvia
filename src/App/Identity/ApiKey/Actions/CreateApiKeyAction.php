<?php

namespace App\Identity\ApiKey\Actions;

use App\Identity\ApiKey\Data\CreateApiKeyData;
use App\Identity\ApiKey\Data\CreatedApiKeyData;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;

/**
 * Creates a personal access token for the user and returns it once with its
 * plain-text value.
 */
class CreateApiKeyAction
{
    public function execute(CreateApiKeyData $data): CreatedApiKeyData
    {
        $token = $data->user->createToken($data->name);

        event(SecurityEventOccurred::for(
            action: SecurityAuditAction::ApiKeyCreated,
            workspaceId: $data->user->workspace()->id,
            actor: SecurityActorData::forUser($data->user),
            targetType: 'api_token',
            targetId: $token->accessToken->id,
            targetLabel: $token->accessToken->name,
        ));

        return new CreatedApiKeyData(
            id: $token->accessToken->id,
            name: $token->accessToken->name,
            plaintext: $token->plainTextToken,
        );
    }
}
