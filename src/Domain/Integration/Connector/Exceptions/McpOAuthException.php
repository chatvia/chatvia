<?php

namespace Domain\Integration\Connector\Exceptions;

use RuntimeException;

/**
 * A connector OAuth step failed in a way the user can act on (the provider
 * refused, the link expired, the flow started in another browser, …). The
 * message is shown to the user as is.
 */
class McpOAuthException extends RuntimeException {}
