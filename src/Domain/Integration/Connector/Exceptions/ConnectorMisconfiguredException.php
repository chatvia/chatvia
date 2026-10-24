<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Exceptions;

use RuntimeException;

/**
 * Raised when a connector is wired for pre-registered OAuth but the
 * admin has not entered its OAuth app (client id and secret) in Settings →
 * Connectors.
 *
 * This is a server-side misconfiguration, not something the end user can act
 * on — the message carries setup detail for the error tracker / local debug
 * and MUST NOT be surfaced to end users in production. Controllers report it
 * and show a generic failure instead. Deliberately NOT a {@see McpOAuthException}
 * so the user-facing OAuth-flow catch blocks never echo its message.
 */
class ConnectorMisconfiguredException extends RuntimeException {}
