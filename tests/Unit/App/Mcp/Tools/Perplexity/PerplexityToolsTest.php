<?php

declare(strict_types=1);

use App\Mcp\McpCallerContext;
use App\Mcp\Servers\PerplexityServer;
use App\Mcp\Tools\Perplexity\PerplexityAskTool;
use App\Mcp\Tools\Perplexity\PerplexityFetchUrlTool;
use App\Mcp\Tools\Perplexity\PerplexityFinanceSearchTool;
use App\Mcp\Tools\Perplexity\PerplexityPeopleSearchTool;
use App\Mcp\Tools\Perplexity\PerplexityResearchTool;
use App\Mcp\Tools\Perplexity\PerplexityRunCodeTool;
use App\Mcp\Tools\Perplexity\PerplexityWebSearchTool;
use Illuminate\Support\Facades\Http;
use Tests\Support\Mcp\InternalMcp;

/**
 * The Perplexity tools wrap the Sonar and Agent APIs with the workspace's own
 * key. These snapshots pin both sides of each call: the exact request sent
 * upstream (the key only as a bearer token) and the exact MCP result the
 * model receives.
 */
beforeEach(function (): void {
    config([
        'services.perplexity.base_url' => 'https://perplexity.example.test',
        'services.perplexity.agent_model' => 'perplexity/sonar',
        'services.perplexity.ask_model' => 'sonar-pro',
        'services.perplexity.research_model' => 'sonar-reasoning-pro',
    ]);
});

/** Act as a caller whose connector forwards this API key (or none). */
function pptCallerKey(?string $key): void
{
    app()->instance(McpCallerContext::class, new McpCallerContext(connectorApiKey: $key));
}

/** Both APIs answering with untidy results: reasoning, duplicate sources, odd items. */
function pptFakeApis(): void
{
    Http::fake([
        'perplexity.example.test/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => "<think>internal reasoning</think>\n  The answer is 42.  "]]],
            'search_results' => [
                ['title' => 'Guide', 'url' => 'https://example.com/guide'],
                ['title' => 'Duplicate', 'url' => 'https://example.com/guide'],
                ['title' => '', 'url' => 'https://example.com/untitled'],
                ['title' => 'No URL'],
            ],
        ]),
        'perplexity.example.test/v1/agent' => Http::response([
            'output' => [
                ['type' => 'web_search_results', 'results' => [
                    ['title' => 'Result A', 'url' => 'https://a.example'],
                    ['title' => 'Result B', 'url' => 'https://b.example', 'sources' => ['https://c.example', 'https://a.example', 7]],
                    'not-an-array',
                ]],
                ['type' => 'fetch_url_results', 'contents' => [['title' => 'Page', 'url' => 'https://page.example']]],
                ['type' => 'sandbox_results', 'language' => 'python', 'code' => 'print(6*7)', 'status' => 'completed', 'results' => [
                    ['stdout' => "42\n", 'stderr' => '', 'exit_code' => 0],
                    'skip-me',
                ]],
                'not-an-item',
                ['type' => 'message', 'role' => 'assistant', 'content' => [['text' => 'Agent says '], ['text' => 'hello.'], ['other' => true]]],
            ],
        ]),
    ]);
}

it('returns a stable Perplexity result', function (string $tool, array $arguments): void {
    pptFakeApis();
    pptCallerKey('pplx-key');

    expect(InternalMcp::resultWithRequestsJson(PerplexityServer::tool($tool, $arguments)))->toMatchSnapshot();
})->with([
    'web_search' => [PerplexityWebSearchTool::class, ['query' => 'latest news']],
    'fetch_url with question' => [PerplexityFetchUrlTool::class, ['url' => 'https://example.com/pricing', 'question' => 'What does it cost?']],
    'fetch_url without question' => [PerplexityFetchUrlTool::class, ['url' => 'https://example.com/pricing']],
    'finance_search' => [PerplexityFinanceSearchTool::class, ['query' => 'NVDA share price']],
    'people_search' => [PerplexityPeopleSearchTool::class, ['query' => 'Lisa Su, CEO of AMD']],
    'run_code' => [PerplexityRunCodeTool::class, ['task' => 'compute 6*7']],
    'ask' => [PerplexityAskTool::class, ['query' => 'why is the sky blue']],
    'research' => [PerplexityResearchTool::class, ['query' => 'history of the euro']],
    'web_search, missing query' => [PerplexityWebSearchTool::class, []],
    'fetch_url, invalid url' => [PerplexityFetchUrlTool::class, ['url' => 'not a url']],
    'run_code, missing task' => [PerplexityRunCodeTool::class, []],
    'ask, query too short' => [PerplexityAskTool::class, ['query' => 'x']],
]);

it('turns a Perplexity failure into a tool error', function (string $tool): void {
    pptCallerKey('pplx-key');
    Http::fake(['perplexity.example.test/*' => Http::response([], 429)]);

    PerplexityServer::tool($tool, ['query' => 'anything'])->assertHasErrors(['Perplexity returned HTTP 429.']);
})->with([
    'a Sonar tool' => PerplexityAskTool::class,
    'an Agent tool' => PerplexityWebSearchTool::class,
]);

it('keeps an agent answer that has citations but no text', function (): void {
    pptCallerKey('pplx-key');
    Http::fake(['perplexity.example.test/v1/agent' => Http::response([
        'output' => [['type' => 'people_search_results', 'results' => [['title' => 'Profile', 'url' => 'https://profile.example']]]],
    ])]);

    expect(InternalMcp::resultWithRequestsJson(PerplexityServer::tool(PerplexityPeopleSearchTool::class, ['query' => 'Jane Doe'])))->toMatchSnapshot();
});

it('falls back to legacy citation URLs on Sonar responses', function (): void {
    pptCallerKey('pplx-key');
    Http::fake(['perplexity.example.test/chat/completions' => Http::response([
        'choices' => [['message' => ['content' => 'Legacy answer.']]],
        'citations' => ['https://one.example', '', 'https://one.example', 'https://two.example'],
    ])]);

    expect(InternalMcp::resultWithRequestsJson(PerplexityServer::tool(PerplexityAskTool::class, ['query' => 'legacy please'])))->toMatchSnapshot();
});

it('refuses Perplexity calls without an API key', function (string $tool, array $arguments): void {
    pptFakeApis();
    pptCallerKey(null);

    PerplexityServer::tool($tool, $arguments)
        ->assertHasErrors(['No Perplexity API key was provided. Reconnect the Perplexity connector with a valid key.']);

    Http::assertNothingSent();
})->with([
    'a Sonar tool' => [PerplexityAskTool::class, ['query' => 'anything']],
    'an Agent tool' => [PerplexityRunCodeTool::class, ['task' => 'anything']],
]);
