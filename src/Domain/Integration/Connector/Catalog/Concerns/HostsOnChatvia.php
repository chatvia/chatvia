<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Catalog\Concerns;

use Illuminate\Support\Str;

/**
 * For a connector whose MCP server this app hosts at `{app.url}/mcp/{slug}`
 * (`App\Mcp\Servers`, routed in routes/ai.php).
 */
trait HostsOnChatvia
{
    public string $mcpUrl {
        get => Str::rtrim((string) config('app.url'), '/')."/mcp/{$this->slug}";
    }

    public string $provider {
        get => 'chatvia';
    }
}
