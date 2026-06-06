<?php

namespace Support\Exceptions;

use RuntimeException;

/**
 * Raised by Support\Http\SafeUrl when a server-side fetch target is unsafe
 * (bad scheme, malformed host, or a host resolving to a private/reserved IP).
 * SSRF guard for function webhooks, MCP servers, OAuth discovery, and crawling.
 */
class UnsafeUrlException extends RuntimeException {}
