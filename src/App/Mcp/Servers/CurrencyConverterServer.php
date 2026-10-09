<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\Currency\ExchangeRateTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

/**
 * The Currency Converter connector (bring-your-own ExchangeRate-API key).
 */
#[Name('Currency Converter')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
Foreign-exchange conversion powered by exchangerate-api. Exposes a single tool:
- exchange-rate: convert any amount between any two currencies (ISO 4217 codes).
Source defaults to USD. Use this when the user asks about currency conversion
or wants to compare prices in different currencies.
TXT)]
class CurrencyConverterServer extends Server
{
    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<Tool>>
     */
    #[\Override]
    protected array $tools = [
        ExchangeRateTool::class,
    ];

    /**
     * @var array<int, class-string<Server\Resource>>
     */
    #[\Override]
    protected array $resources = [];

    /**
     * @var array<int, class-string<Prompt>>
     */
    #[\Override]
    protected array $prompts = [];
}
