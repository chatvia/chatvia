<?php

declare(strict_types=1);

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Tooling\Routing\SemanticToolRouter;
use Domain\Conversation\Services\Tooling\Routing\ToolHybridSearch;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpServerTool;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Ingestion\Services\Search\Bm25Tokenizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\FakeEmbeddingsProvider;

/**
 * OFFLINE EVAL — not a pass/fail gate. It measures the live tool router against a
 * labelled set (fixtures/cases.php + fixtures/connectors.php; format in README.md)
 * and prints a scoreboard:
 * top-1 accuracy, recall@5, MRR, and the metric that actually matters for tools —
 * the WRONG-MUTATION rate (a write tool from the wrong connector surfacing for a
 * request). "Relevance is not correctness": a retriever that surfaces a usable
 * tool can still surface the wrong mutating one.
 *
 * It runs the REAL embeddings provider + reranker (the fake one in TestCase emits
 * non-semantic crc32 vectors, which would make every number noise). So it needs a
 * configured embedding API key and network. With no key — i.e. normal CI — it
 * skips itself cleanly. Run it on demand:
 *
 *     # with the embedding provider configured (the default `qwen` + SCALEWAYAI_API_KEY)
 *     SCALEWAYAI_API_KEY=… vendor/bin/pest --testsuite=Eval
 *
 * Tune the per-scope bars in the same run by overriding the env:
 *
 *     SCALEWAYAI_API_KEY=… AGENT_TOOL_RELEVANCE_THRESHOLD_WRITE=0.60 vendor/bin/pest --testsuite=Eval
 *
 * Or A/B the experimental small-model connector router (drops writes from
 * connectors it doesn't pick for the turn) against the default:
 *
 *     SCALEWAYAI_API_KEY=… AGENT_TOOL_CONNECTOR_CLASSIFIER=1 vendor/bin/pest --testsuite=Eval
 *
 * Establish a baseline now, change a threshold (#1 per-scope gating), re-run, diff
 * the scoreboard. The number it prints is the thing you're trying to move.
 */
beforeEach(function (): void {
    if (! Schema::hasTable('connector_tools') || ! Schema::hasColumn('connector_tools', 'scope')) {
        $this->markTestSkipped('schema out of date — run migrate:fresh on the testing DB.');
    }

    // Swap the offline fake (bound in TestCase) for the real, config-selected
    // provider, then pin that one instance so the helpers, hybrid search and the
    // relevance gate all score with the same real embeddings + reranker.
    app()->forgetInstance(EmbeddingsProviderInterface::class);
    $provider = app(EmbeddingsProviderInterface::class);

    if ($provider instanceof FakeEmbeddingsProvider) {
        $this->markTestSkipped('real embeddings provider not bound; cannot run a semantic eval against the fake.');
    }

    // Health probe — the real provider needs an API key + network. No key (normal
    // CI) → skip cleanly rather than fail. This is also why the eval is excluded
    // from the default suite without any group plumbing.
    try {
        $provider->embedQuery('health check');
    } catch (Throwable $e) {
        $this->markTestSkipped(
            'real embeddings unavailable (configure the embedding provider + API key, then re-run with --testsuite=Eval): '.$e->getMessage()
        );
    }

    app()->instance(EmbeddingsProviderInterface::class, $provider);
    $this->embeddings = $provider;

    // The relevance gate scores with the reranker, which TestCase also fakes —
    // swap in the real, config-selected one.
    app()->forgetInstance(Reranker::class);
    app()->instance(Reranker::class, app(Reranker::class));
});

it('measures tool-routing retrieval quality against the labelled set', function (): void {
    $data = [
        'catalog' => require __DIR__.'/fixtures/connectors.php',
        'cases' => require __DIR__.'/fixtures/cases.php',
    ];

    // Validate the fixture before scoring: every case must point at a tool that
    // exists in the catalog, with a scope that matches its `write` label. A typo'd
    // tool name or a mislabelled read/write would otherwise silently score 0 —
    // fail loudly with the offending rows instead.
    $catalogScopes = [];
    foreach ($data['catalog'] as $slug => $connector) {
        foreach ($connector['tools'] as $tool) {
            $catalogScopes[$slug.'/'.$tool['name']] = $tool['scope'];
        }
    }
    $fixtureErrors = [];
    foreach ($data['cases'] as $i => $case) {
        $key = $case['connector'].'/'.$case['tool'];
        if (! isset($catalogScopes[$key])) {
            $fixtureErrors[] = "case #{$i} \"{$case['query']}\": tool '{$key}' is not in connectors.php.";

            continue;
        }
        if ($case['write'] !== ($catalogScopes[$key] === 'write')) {
            $fixtureErrors[] = "case #{$i} \"{$case['query']}\": write=".($case['write'] ? 'true' : 'false')
                ." but connectors.php scopes '{$key}' as '{$catalogScopes[$key]}'.";
        }
    }
    if ($fixtureErrors !== []) {
        throw new RuntimeException("Eval fixture invalid:\n  - ".implode("\n  - ", $fixtureErrors));
    }

    // scope lookup keyed by compiled tool name, so we can classify whatever the
    // router ships (mcp__{slug}__{tool}) back to read/write for the mutation metric.
    $scopeByName = [];

    // --- Seed the catalog as an agent's reachable preset connectors, embedded for real. ---
    $agent = Agent::factory()->create();

    foreach ($data['catalog'] as $slug => $connector) {
        $server = AgentMcpServer::create([
            'user_id' => $agent->user_id,
            'connector_slug' => $slug,
            'uses_central_catalog' => true,
            'name' => $connector['name'],
            'slug' => $slug,
            'url' => "https://example.test/mcp/{$slug}",
            'transport' => 'streamable_http',
            'auth_type' => 'none',
            'is_enabled' => true,
        ]);

        DB::table('agent_mcp_server')->insert(['agent_id' => $agent->id, 'mcp_server_id' => $server->id]);

        foreach ($connector['tools'] as $tool) {
            // Mirror EmbedMcpToolsAction::executeForConnector — connector context
            // is embedded alongside the tool so an ambiguous name still carries
            // its brand signal.
            $text = trim($connector['name']."\n".$connector['description']."\n\n".$tool['name']."\n\n".$tool['description']);

            $row = ConnectorTool::factory()->create([
                'connector_slug' => $slug,
                'name' => $tool['name'],
                'description' => $tool['description'],
                'scope' => McpToolScope::from($tool['scope']),
            ]);

            $row->forceFill([
                'embedding' => embed($text),
                'embedding_bm25' => Bm25Tokenizer::expression($text),
            ])->save();

            McpServerTool::create([
                'mcp_server_id' => $server->id,
                'connector_tool_id' => $row->id,
                'permission' => McpToolPermission::Allow,
            ]);

            $scopeByName['mcp__'.$slug.'__'.$tool['name']] = $tool['scope'];
        }
    }

    // read/write classification of a shipped tool; null = not in our catalog
    // (e.g. a global builtin), treated as non-mutating for the wrong-write metric.
    $scopeOf = static fn (string $compiledName): ?string => $scopeByName[$compiledName] ?? null;
    $connectorOf = static function (string $compiledName): ?string {
        $parts = explode('__', $compiledName); // mcp__{slug}__{tool}

        return $parts[1] ?? null;
    };

    $search = app(ToolHybridSearch::class);
    $router = app(SemanticToolRouter::class);

    $rows = [];
    $agg = [
        'top1' => 0, 'recall5' => 0, 'mrr' => 0.0,
        'shipped_top1' => 0, 'shipped_recall' => 0, 'shipped_size' => 0,
        'wrong_write_top1' => 0, 'wrong_write_in_set' => 0,
        'write_cases' => 0, 'write_starved' => 0,
    ];

    foreach ($data['cases'] as $case) {
        $expected = 'mcp__'.$case['connector'].'__'.$case['tool'];
        $queryVector = $this->embeddings->embedQuery($case['query']);

        // Pre-gate ranking for the recall/MRR metrics.
        $ranked = $search->search($agent, $case['query'], $queryVector, SemanticToolRouter::MAX_TOOLS);
        $rankedNames = array_map(static fn (array $r): string => $r['entry']->name, $ranked);

        // Shipped set via the REAL router (hybrid search → per-scope relevance
        // gate), so the eval always reflects the full live pipeline.
        $shipped = $router->route($case['query'], $agent);
        $shippedNames = array_map(static fn ($e): string => $e->name, $shipped);

        $rank = array_search($expected, $rankedNames, true);
        $rank = $rank === false ? null : $rank + 1;

        $top1 = ($rankedNames[0] ?? null) === $expected;
        $recall5 = $rank !== null && $rank <= 5;
        $rr = $rank !== null ? 1.0 / $rank : 0.0;

        $shippedTop1Name = $shippedNames[0] ?? null;
        $shippedTop1 = $shippedTop1Name === $expected;
        // Did the correct tool survive the gate at all? ship#1 ✗ + in-set ✓ is
        // a harmless demotion (the model can still pick it); in-set ✗ on a write
        // is STARVATION — the bar dropped the right mutation and the model can't
        // call it. That's the cost the write threshold has to be tuned against.
        $expectedInShipped = in_array($expected, $shippedNames, true);

        // The metric that matters: a WRITE tool from a DIFFERENT connector than
        // the labelled answer. In-set = the model could pick it; top1 = the
        // router actively put a wrong mutation first.
        $wrongWriteInSet = false;
        foreach ($shippedNames as $name) {
            if ($name !== $expected && $scopeOf($name) === 'write' && $connectorOf($name) !== $case['connector']) {
                $wrongWriteInSet = true;
                break;
            }
        }
        $wrongWriteTop1 = $shippedTop1Name !== null
            && $shippedTop1Name !== $expected
            && $scopeOf($shippedTop1Name) === 'write'
            && $connectorOf($shippedTop1Name) !== $case['connector'];

        $agg['top1'] += $top1 ? 1 : 0;
        $agg['recall5'] += $recall5 ? 1 : 0;
        $agg['mrr'] += $rr;
        $agg['shipped_top1'] += $shippedTop1 ? 1 : 0;
        $agg['shipped_recall'] += $expectedInShipped ? 1 : 0;
        $agg['shipped_size'] += count($shippedNames);
        if ($case['write']) {
            $agg['write_cases']++;
            $agg['wrong_write_top1'] += $wrongWriteTop1 ? 1 : 0;
            $agg['wrong_write_in_set'] += $wrongWriteInSet ? 1 : 0;
            $agg['write_starved'] += $expectedInShipped ? 0 : 1;
        }

        $rows[] = [
            'query' => $case['query'],
            'expected' => $case['connector'].'/'.$case['tool'],
            'write' => $case['write'],
            'ship1' => $shippedTop1,
            'in_set' => $expectedInShipped,
            'rank' => $rank,
            'wrong_write' => $wrongWriteTop1 ? 'TOP1' : ($wrongWriteInSet ? 'set' : ''),
        ];
    }

    $n = max(count($data['cases']), 1);
    $w = max($agg['write_cases'], 1);

    // --- Scoreboard ---
    $pct = static fn (float $v): string => str_pad(number_format($v * 100, 1).'%', 7, ' ', STR_PAD_LEFT);
    $out = "\n".str_repeat('=', 96)."\n";
    $fallback = config('chatvia.agent.tools.routing.relevance_threshold', 0.55);
    $out .= "TOOL-ROUTING EVAL  ·  provider={$this->embeddings->getModelName()}"
        .'  ·  gate='.(config('chatvia.agent.tools.routing.relevance_gate') ? 'on' : 'off')
        .'  ·  threshold read='.config('chatvia.agent.tools.routing.relevance_threshold_read', $fallback)
        .' / write='.config('chatvia.agent.tools.routing.relevance_threshold_write', $fallback)
        .'  ·  connector_router='.(config('chatvia.agent.tools.routing.connector_classifier') ? 'on' : 'off')."\n";
    $out .= str_repeat('=', 96)."\n";
    $out .= sprintf("%-40s %-30s %-7s %-7s %-5s %s\n", 'query', 'expected', 'ship#1', 'in-set', 'rank', 'wrong-write');
    $out .= str_repeat('-', 96)."\n";
    foreach ($rows as $r) {
        // Flag a starved write: correct mutation dropped out of the shipped set.
        $starved = $r['write'] && ! $r['in_set'];
        $out .= sprintf(
            "%-40s %-30s %-7s %-7s %-5s %s\n",
            mb_strimwidth($r['query'], 0, 39, '…'),
            mb_strimwidth($r['expected'].($r['write'] ? ' [w]' : ''), 0, 29, '…'),
            $r['ship1'] ? '   ✓' : '   ✗',
            $r['in_set'] ? '   ✓' : ($starved ? '  ✗!' : '   ✗'),
            $r['rank'] ?? '—',
            $r['wrong_write'],
        );
    }
    $out .= str_repeat('-', 96)."\n";
    $out .= 'top-1 accuracy (pre-gate) : '.$pct($agg['top1'] / $n)."   ({$agg['top1']}/{$n})\n";
    $out .= 'recall@5                  : '.$pct($agg['recall5'] / $n)."   ({$agg['recall5']}/{$n})\n";
    $out .= 'MRR                       : '.str_pad(number_format($agg['mrr'] / $n, 3), 7, ' ', STR_PAD_LEFT)."\n";
    $out .= 'shipped top-1 accuracy    : '.$pct($agg['shipped_top1'] / $n)."   ({$agg['shipped_top1']}/{$n})\n";
    $out .= 'shipped recall (in set)   : '.$pct($agg['shipped_recall'] / $n)."   ({$agg['shipped_recall']}/{$n})\n";
    $out .= 'avg tools shipped / turn  : '.number_format($agg['shipped_size'] / $n, 1)."\n";
    $out .= str_repeat('-', 96)."\n";
    $out .= 'WRONG-MUTATION (write cases only, n='.$agg['write_cases'].")\n";
    $out .= '  wrong write as top-1     : '.$pct($agg['wrong_write_top1'] / $w)."   ({$agg['wrong_write_top1']}/{$agg['write_cases']})\n";
    $out .= '  wrong write in shipped   : '.$pct($agg['wrong_write_in_set'] / $w)."   ({$agg['wrong_write_in_set']}/{$agg['write_cases']})\n";
    $out .= '  correct write STARVED    : '.$pct($agg['write_starved'] / $w)."   ({$agg['write_starved']}/{$agg['write_cases']})   <- write bar dropped the right tool\n";
    $out .= str_repeat('=', 96)."\n";

    fwrite(STDOUT, $out);

    // Structural assertion only — this is a measurement baseline, not a gate, so
    // it never blocks on a threshold. It just confirms the harness evaluated
    // every labelled case against the live router.
    expect($rows)->toHaveCount(count($data['cases']));
})->group('eval');
