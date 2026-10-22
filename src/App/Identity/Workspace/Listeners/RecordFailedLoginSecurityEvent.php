<?php

namespace App\Identity\Workspace\Listeners;

use App\Identity\Workspace\Models\User;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Enums\SecurityAuditOutcome;
use Domain\Security\Events\SecurityEventOccurred;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Records a failed sign-in attempt — a real brute-force / credential-stuffing
 * signal. Captures the attempted email ONLY; the password is never read or
 * stored.
 *
 * The row is tenant-scoped, so an attempt is recorded only when the email
 * resolves to a known user (we then know the workspace). Attempts against
 * non-existent emails have no tenant to surface them to and are skipped.
 *
 * NOT queued — reads live IP/UA off the request, like {@see RecordKnownDevice}.
 */
class RecordFailedLoginSecurityEvent
{
    public function __construct(private readonly Request $request) {}

    public function handle(Failed $event): void
    {
        $email = is_string($event->credentials['email'] ?? null) ? $event->credentials['email'] : null;

        $user = $event->user instanceof User
            ? $event->user
            : ($email !== null ? User::query()->where('email', Str::lower($email))->first() : null);

        if (! $user instanceof User) {
            return;
        }

        event(SecurityEventOccurred::for(
            action: SecurityAuditAction::AuthLoginFailed,
            workspaceId: $user->workspace()->id,
            actor: SecurityActorData::forUser($user, $this->request->ip(), $this->request->userAgent()),
            metadata: ['email' => $email],
            outcome: SecurityAuditOutcome::Failure,
        ));
    }
}
