<?php

declare(strict_types=1);

namespace Domain\Analytics\Events;

/**
 * Old name of {@see \Domain\Integration\Mcp\Events\McpToolInvoked}. A queued
 * listener job stores the event's class name, so this alias lets a job queued
 * under the old name still unserialize.
 *
 * @deprecated Use \Domain\Integration\Mcp\Events\McpToolInvoked. Delete once no
 *             queued job refers to this name.
 */
final class McpToolInvoked extends \Domain\Integration\Mcp\Events\McpToolInvoked {}
