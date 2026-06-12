<?php

namespace App\Identity\ApiKey\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;

class DeleteApiKeyAction
{
    public function execute(User $user, int $tokenId): void
    {
        $deleted = $user->tokens()->whereKey($tokenId)->delete();

        if ($deleted === 0) {
            return;
        }

        event(SecurityEventOccurred::for(
            action: SecurityAuditAction::ApiKeyDeleted,
            workspaceId: $user->workspace()->id,
            actor: SecurityActorData::forUser($user),
            targetType: 'api_token',
            targetId: $tokenId,
        ));
    }
}
