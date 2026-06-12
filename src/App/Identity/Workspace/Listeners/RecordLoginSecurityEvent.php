<?php

namespace App\Identity\Workspace\Listeners;

use App\Identity\Workspace\Models\User;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Listeners\RecordSecurityEvent;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

/**
 * Records a successful sign-in to the security audit log. The SECOND Login
 * listener (alongside {@see RecordKnownDevice}).
 *
 * NOT queued — it reads the live IP + User-Agent from the HTTP request and
 * denormalizes them onto the event; a queued worker would inject a fresh CLI
 * request (UA "Symfony", IP 127.0.0.1) and defeat the audit, the same trap
 * RecordKnownDevice documents. The DB write itself is queued downstream by
 * {@see RecordSecurityEvent}.
 */
class RecordLoginSecurityEvent
{
    public function __construct(private readonly Request $request) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        event(SecurityEventOccurred::for(
            action: SecurityAuditAction::AuthLogin,
            workspaceId: $event->user->workspace()->id,
            actor: SecurityActorData::forUser($event->user, $this->request->ip(), $this->request->userAgent()),
        ));
    }
}
