<?php

declare(strict_types=1);

namespace App\Identity\Chatter\Actions;

use App\Identity\Chatter\Data\BlockChatterData;
use App\Identity\Chatter\Events\ChatterBlocked;
use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;

/**
 * Blocks a chatter who abused an agent (from the Guardrails Activity log):
 * switches their account off with the reason, which signs them out of every
 * agent of the workspace on their next request, then tells them by email when
 * asked ({@see ChatterBlocked}). The block goes to the security audit log.
 * Turning the account back on is done on the People page.
 */
class BlockChatterAction
{
    public function __construct(
        private readonly DeactivateAccountAction $deactivate,
    ) {}

    public function execute(BlockChatterData $data): void
    {
        $chatter = $data->chatter;

        $this->deactivate->execute($chatter, $data->reason);

        event(new ChatterBlocked($chatter, $data->notice));

        event(SecurityEventOccurred::for(
            SecurityAuditAction::ChatterBlocked,
            $chatter->workspace_id,
            $data->actor,
            targetType: 'chatter',
            targetId: $chatter->external_id,
            targetLabel: $chatter->name,
            metadata: array_filter([
                'agent_id' => $data->agentId,
                'notice' => $data->notice->value,
            ]),
        ));
    }
}
