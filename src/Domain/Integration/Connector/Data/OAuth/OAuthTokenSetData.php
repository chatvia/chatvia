<?php

namespace Domain\Integration\Connector\Data\OAuth;

use Carbon\CarbonImmutable;

/**
 * The tokens an OAuth token endpoint returned (RFC 6749 §5.1), wrapped right
 * after decoding the response.
 */
final readonly class OAuthTokenSetData
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken = null,
        public ?int $expiresIn = null,
        public ?string $scope = null,
    ) {}

    /**
     * @param  array<string, mixed>  $response  A decoded token-endpoint response that carries an `access_token`.
     */
    public static function fromResponse(array $response): self
    {
        return new self(
            accessToken: (string) $response['access_token'],
            refreshToken: isset($response['refresh_token']) ? (string) $response['refresh_token'] : null,
            expiresIn: isset($response['expires_in']) ? (int) $response['expires_in'] : null,
            scope: isset($response['scope']) ? (string) $response['scope'] : null,
        );
    }

    /**
     * When the access token stops working, or null when the server did not say.
     */
    public function expiresAt(): ?CarbonImmutable
    {
        return $this->expiresIn !== null ? CarbonImmutable::now()->addSeconds($this->expiresIn) : null;
    }
}
