<?php

use App\Mcp\Servers\ChartsServer;
use App\Mcp\Servers\CurrencyConverterServer;
use App\Mcp\Servers\WeatherServer;
use Laravel\Mcp\Facades\Mcp;

/**
 * Built-in MCP servers exposed by ChatVia itself. Each one is registered as
 * a featured connector in config/connectors.php so customers can attach
 * them per-agent like any third-party MCP (Stripe, Notion, etc.). Splitting
 * the catalogue into one server per concern keeps tool descriptions tight
 * and lets users opt into individual capabilities.
 */
Mcp::web('/mcp/weather', WeatherServer::class);
Mcp::web('/mcp/currency', CurrencyConverterServer::class);
Mcp::web('/mcp/charts', ChartsServer::class);
