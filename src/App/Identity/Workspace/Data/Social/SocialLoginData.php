<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Data\Social;

use App\Identity\Workspace\Enums\SocialProvider;

/**
 * The identity an OAuth provider returned for a sign-in: who it is at the
 * provider and the email it gave. `emailVerified` is null when the provider
 * does not say, and false when it says the email is NOT verified, in which
 * case the same-email auto-link is refused so an unverified provider email
 * cannot take over an existing account.
 */
final readonly class SocialLoginData
{
    public function __construct(
        public SocialProvider $provider,
        public string $providerId,
        public ?string $email,
        public ?bool $emailVerified = null,
    ) {}
}
