<?php

namespace App\Identity\Workspace\Listeners;

use App\Identity\Workspace\Models\User;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;

/**
 * Records a sign-out to the security audit log. NOT queued — reads the live
 * IP/UA off the request, like {@see RecordKnownDevice}.
 */
class RecordLogoutSecurityEvent
{
    public function __construct(private readonly Request $request) {}

    public function handle(Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        event(SecurityEventOccurred::for(
            action: SecurityAuditAction::AuthLogout,
            workspaceId: $event->user->workspace()->id,
            actor: SecurityActorData::forUser($event->user, $this->request->ip(), $this->request->userAgent()),
        ));
    }
}
