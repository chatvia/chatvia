<?php

namespace Domain\Security\Data;

use App\Identity\Workspace\Models\User;
use Domain\Security\Events\SecurityEventOccurred;

/**
 * The denormalized causer of a security event — captured at the boundary (the
 * acting user plus the live request IP/User-Agent) and carried as scalars into
 * {@see SecurityEventOccurred}. Keeping it a plain value
 * object lets Request-free Domain Actions emit audit events without reaching for
 * the HTTP layer: the controller/listener fills it, the Action just forwards it.
 */
final readonly class SecurityActorData
{
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?string $email = null,
        public ?string $ip = null,
        public ?string $userAgent = null,
    ) {}

    public static function forUser(User $user, ?string $ip = null, ?string $userAgent = null): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            ip: $ip,
            userAgent: $userAgent,
        );
    }

    /**
     * A non-user actor (scheduled job, system process). No id/name/email; the
     * row still records the action and any captured request context.
     */
    public static function system(?string $ip = null, ?string $userAgent = null): self
    {
        return new self(ip: $ip, userAgent: $userAgent);
    }
}
