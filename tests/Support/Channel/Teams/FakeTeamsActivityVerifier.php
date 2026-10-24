<?php

declare(strict_types=1);

namespace Tests\Support\Channel\Teams;

use Domain\Channel\Teams\Services\TeamsActivityVerifier;

/**
 * Stand-in for the JWT half of {@see TeamsActivityVerifier}, so webhook tests
 * never sign a token: `verify()` accepts the request while `$valid` and hands
 * back `$claims` (every connector token names the activity's service URL),
 * recording the App ID it was asked to check. `matchesServiceUrl()` is the
 * real one; TeamsActivityVerifierTest covers the crypto.
 */
class FakeTeamsActivityVerifier extends TeamsActivityVerifier
{
    public bool $valid = true;

    /** @var array<string, mixed> */
    public array $claims = ['serviceurl' => 'https://smba.trafficmanager.net/emea/'];

    public ?string $checkedAppId = null;

    public function verify(?string $authorization, string $appId): ?array
    {
        $this->checkedAppId = $appId;

        return $this->valid ? $this->claims : null;
    }
}
