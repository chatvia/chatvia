<?php

declare(strict_types=1);

namespace Domain\Analytics\Events;

/**
 * @deprecated Moved to \Domain\Integration\Mcp\Events\McpToolInvoked; kept so queued listener jobs serialized before the deploy still unserialize. Remove after the next release.
 */
final class McpToolInvoked extends \Domain\Integration\Mcp\Events\McpToolInvoked {}
