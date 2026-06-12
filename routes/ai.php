<?php

use App\Http\Middleware\Auth\VerifyInternalMcpRequest;
use Domain\Integration\Mcp\Services\Servers\AccountAnalyticsServer;
use Domain\Integration\Mcp\Services\Servers\ChartsServer;
use Domain\Integration\Mcp\Services\Servers\CurrencyConverterServer;
use Domain\Integration\Mcp\Services\Servers\LeadsServer;
use Domain\Integration\Mcp\Services\Servers\PerplexityServer;
use Domain\Integration\Mcp\Services\Servers\WeatherServer;
use Laravel\Mcp\Facades\Mcp;

/**
 * Built-in MCP servers exposed by ChatVia itself. Each one is registered as
 * a featured connector in config/connectors.php so customers can attach
 * them per-agent like any third-party MCP (Stripe, Notion, etc.). Splitting
 * the catalogue into one server per concern keeps tool descriptions tight
 * and lets users opt into individual capabilities.
 *
 * These are public HTTP routes but are NOT for the public: VerifyInternalMcpRequest
 * requires a fresh app-signed HMAC (so only Chatvia's own server-side tool loop
 * can reach them) and, for tools/call, that the calling agent has the connector
 * enabled. A per-agent throttle backs it as a runaway-loop guard.
 */
Mcp::web('/mcp/weather', WeatherServer::class)->middleware([VerifyInternalMcpRequest::class, 'throttle:builtin-mcp']);
Mcp::web('/mcp/currency', CurrencyConverterServer::class)->middleware([VerifyInternalMcpRequest::class, 'throttle:builtin-mcp']);
Mcp::web('/mcp/charts', ChartsServer::class)->middleware([VerifyInternalMcpRequest::class, 'throttle:builtin-mcp']);
Mcp::web('/mcp/leads', LeadsServer::class)->middleware([VerifyInternalMcpRequest::class, 'throttle:builtin-mcp']);
Mcp::web('/mcp/perplexity', PerplexityServer::class)->middleware([VerifyInternalMcpRequest::class, 'throttle:builtin-mcp']);

/**
 * Internal, owner-scoped analytics server. NOT a featured connector and never
 * attached to an agent — it answers the workspace owner's "Ask your data" chat.
 * Same HMAC gate; VerifyInternalMcpRequest also accepts a signed
 * X-Chatvia-Workspace-Id here (workspace mode) instead of a per-agent check.
 */
Mcp::web('/mcp/account-analytics', AccountAnalyticsServer::class)->middleware([VerifyInternalMcpRequest::class, 'throttle:builtin-mcp']);
