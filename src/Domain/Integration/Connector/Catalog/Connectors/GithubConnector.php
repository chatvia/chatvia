<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Connectors;

use Domain\Integration\Connector\Catalog\PreRegisteredOAuthConnector;
use Domain\Integration\Connector\Enums\ConnectorCategory;

/**
 * GitHub's hosted MCP server. GitHub has no dynamic client registration, so the admin
 * registers an OAuth App and enters it in Settings → Connectors.
 */
final class GithubConnector extends PreRegisteredOAuthConnector
{
    #[\Override]
    public string $slug { get => 'github'; }

    #[\Override]
    public string $name { get => 'GitHub'; }

    #[\Override]
    public string $description { get => 'Read repos, issues, PRs, and code from any account you have access to.'; }

    #[\Override]
    public ConnectorCategory $category { get => ConnectorCategory::DeveloperTools; }

    #[\Override]
    public string $brandColor { get => '181717'; }

    #[\Override]
    public int $priority { get => 70; }

    #[\Override]
    public string $mcpUrl { get => 'https://api.githubcopilot.com/mcp/'; }

    #[\Override]
    public string $authorizationEndpoint { get => 'https://github.com/login/oauth/authorize'; }

    #[\Override]
    public string $tokenEndpoint { get => 'https://github.com/login/oauth/access_token'; }

    #[\Override]
    public string $scopes { get => 'repo read:org read:user user:email gist'; }

    #[\Override]
    public ?string $appConsoleUrl { get => 'https://github.com/settings/developers'; }
}
