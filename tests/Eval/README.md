# Tool-routing eval

An **offline measurement harness** for the agent tool router
(`SemanticToolRouter` → `ToolHybridSearch` → `ToolRelevanceGate` → optional
`ConnectorClassifier`). It is **not a pass/fail CI gate** — it runs the *real*
Qwen3 embeddings + reranker against a labeled set and prints a scoreboard, so you
can change a knob, re-run, and see the number move.

> Why real models? The `FakeEmbeddingsProvider` bound in tests emits non-semantic
> crc32 vectors — every retrieval number would be noise. The eval swaps in the
> configured provider, so it needs an API key + network. **With no key (normal CI)
> it skips itself** — that's also why it's excluded from the default suite without
> any group plumbing.

## Run it

```bash
# baseline (defaults: read 0.45 / write 0.55, connector router off)
SCALEWAYAI_API_KEY=… vendor/bin/pest --testsuite=Eval
```

Use `--testsuite=Eval` — **not** `--group=eval` (that trips a Pest TIA crash on
this PHP build). Knobs are env overrides, applied for one run:

| Env var | What it does |
|---|---|
| `AGENT_TOOL_RELEVANCE_THRESHOLD_READ` | read-tool relevance bar (recall) |
| `AGENT_TOOL_RELEVANCE_THRESHOLD_WRITE` | write-tool relevance bar (precision) |
| `AGENT_TOOL_CONNECTOR_CLASSIFIER` | `1` = enable the small-model connector router |

The winning combo so far — loose write bar (no starvation) + router (precision):

```bash
SCALEWAYAI_API_KEY=… AGENT_TOOL_RELEVANCE_THRESHOLD_WRITE=0.45 AGENT_TOOL_CONNECTOR_CLASSIFIER=1 \
  vendor/bin/pest --testsuite=Eval
```

## The two fixtures

| File | What it is | When you edit it |
|---|---|---|
| [`fixtures/cases.php`](fixtures/cases.php) | labeled queries: `query → the one tool a human would pick` | **often** — this is what you grow |
| [`fixtures/connectors.php`](fixtures/connectors.php) | the connector catalog the eval seeds + embeds | when your real connectors/tools change |

Each run, the eval seeds `connectors.php` as a fresh agent's reachable preset
connectors (embedding every tool for real), then scores each query in `cases.php`
against the live router. Everything runs in a rolled-back transaction — nothing
persists.

## Add a real labeled query

This is the high-value loop. Pull anonymized queries from your logs and label them:

1. Open [`fixtures/cases.php`](fixtures/cases.php) and add a row:
   ```php
   ['query' => 'cancel my subscription', 'connector' => 'stripe', 'tool' => 'cancel_subscription', 'write' => true],
   ```
2. `connector` + `tool` must exist in `connectors.php`, and `write` must match that
   tool's `scope`. The eval **validates this before scoring** and fails with the
   offending rows if not — so a typo can't silently score 0.
3. Re-run. The more rows (aim for 50+), the less a single case sways the result
   (one row ≈ `100/N %`), and the closer the scoreboard gets to your real traffic.

Cover a spread of reads and writes, and include the confusable pairs
(ticket vs issue, refund vs invoice) — those are where the router earns its keep.

## Mirror your real catalog

To score against *your* connectors instead of the synthetic set, dump your real
preset catalog into the `connectors.php` shape. Run this against a DB that has your
connectors (dev/prod tinker), then paste the output over `connectors.php`:

```php
// php artisan tinker
use Domain\Integration\Mcp\Models\ConnectorTool;
use Illuminate\Support\Facades\DB;

$catalog = [];
foreach (ConnectorTool::query()->orderBy('connector_slug')->orderBy('name')->get() as $t) {
    $slug = $t->connector_slug;
    if (! isset($catalog[$slug])) {
        $server = DB::table('mcp_servers')->where('connector_slug', $slug)->first();
        $catalog[$slug] = [
            'name' => $server->name ?? ucfirst($slug),
            'description' => (string) ($server->description ?? ''),
            'tools' => [],
        ];
    }
    $catalog[$slug]['tools'][] = [
        'name' => $t->name,
        'scope' => $t->scope->value,
        'description' => (string) $t->description,
    ];
}
echo "<?php\n\nreturn ".var_export($catalog, true).";\n";
```

(Custom, per-workspace MCP servers live in `mcp_tools` joined to `mcp_servers` —
same idea if you want those too.) Then label real queries against the real tool
names in `cases.php`.

## Reading the scoreboard

| Metric | Meaning |
|---|---|
| `shipped top-1` | the human-picked tool was the **first** tool shipped to the model |
| `shipped recall (in set)` | the picked tool was **in** the shipped set at all — `< 100%` on a write = **starvation** (the model literally can't call it) |
| `wrong write in shipped` | a write tool from the **wrong connector** was in the set (the dangerous one) |
| `wrong write as top-1` | the router put a wrong mutation **first** |
| `correct write STARVED` | a write the gate/bar dropped — the cost of too strict a write threshold |
| `avg tools / turn` | context size; the original "don't dump the whole catalog" pressure |

Rule of thumb: **starvation is worse than a wrong write in the set** — a wrong
write is never top-1 here and write tools already gate through the approval
checkpoint, but a starved tool can't be called at all.
