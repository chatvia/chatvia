<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog;

use Domain\Integration\Connector\Enums\ConnectorAuthStrategy;

/**
 * A connector whose OAuth server has no dynamic registration, so an admin
 * registers one OAuth app for the install and enters it in Settings →
 * Connectors (one card per connector of this kind). The client is read from
 * `connectors.oauth_apps.{slug}` on every call, so a rotated secret applies
 * at once; `App\Admin\Settings\ConnectorSettings` fills those keys.
 */
abstract class PreRegisteredOAuthConnector extends Connector
{
    abstract public string $authorizationEndpoint { get; }

    abstract public string $tokenEndpoint { get; }

    /** Space-separated scopes, as the provider expects them. */
    abstract public string $scopes { get; }

    /** Where the admin creates the OAuth app; null when the provider has no single page for it. */
    abstract public ?string $appConsoleUrl { get; }

    #[\Override]
    public ConnectorAuthStrategy $authStrategy {
        get => ConnectorAuthStrategy::Oauth;
    }

    /** The config key of one field (`client_id`, `client_secret`) of the admin's OAuth app. */
    public function oauthAppConfigKey(string $field): string
    {
        return "connectors.oauth_apps.{$this->slug}.{$field}";
    }

    #[\Override]
    protected function extraData(): array
    {
        return [
            'oauthClientId' => config($this->oauthAppConfigKey('client_id')),
            'oauthClientSecret' => config($this->oauthAppConfigKey('client_secret')),
            'oauthAuthorizationEndpoint' => $this->authorizationEndpoint,
            'oauthTokenEndpoint' => $this->tokenEndpoint,
            'oauthScopes' => $this->scopes,
        ];
    }
}
