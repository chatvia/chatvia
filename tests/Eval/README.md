# Evals

Three offline measurement harnesses, all in the `Eval` test suite. They print
a scoreboard or a report and skip themselves without what they need (keys, or
a folder of sample documents). None is a pass/fail gate. Run them with
`--testsuite=Eval`.

- [Knowledge retrieval](#knowledge-retrieval-eval): does the agent's search find the
  passage that answers a question, and nothing for a question its knowledge
  does not cover?
- [Tool routing](#tool-routing-eval): does the router give the model the right tool?
- [Document readers](#document-reader-eval): is the built-in PHP reader of a
  format good enough for servers without the program it stands in for?

The two retrieval harnesses run the *real* embeddings and reranker
(`evalUseRealModels()` in `tests/Pest.php`): a provider key given on the
command line, named after the provider's config block (`SCALEWAYAI_API_KEY`,
`OPENROUTER_API_KEY`, `COHERE_API_KEY`, `GEMINI_API_KEY`, `VOYAGE_API_KEY`…),
goes into config, as the admin panel puts it there.

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
# baseline (connector router off)
SCALEWAYAI_API_KEY=… vendor/bin/pest --testsuite=Eval

# or through OpenRouter (same Qwen3 embeddings, no Scaleway account)
OPENROUTER_API_KEY=… EVAL_EMBEDDING_PROVIDER=openrouter \
  EVAL_EMBEDDING_MODEL=qwen/qwen3-embedding-8b vendor/bin/pest --testsuite=Eval
```

Use `--testsuite=Eval` — **not** `--group=eval` (that trips a Pest TIA crash on
this PHP build). Knobs are env overrides, applied for one run:

| Env var | What it does |
|---|---|
| `AGENT_TOOL_RELEVANCE_THRESHOLD_READ` | read-tool relevance bar (recall) |
| `AGENT_TOOL_RELEVANCE_THRESHOLD_WRITE` | write-tool relevance bar (precision) |
| `AGENT_TOOL_CONNECTOR_CLASSIFIER` | `1` = enable the small-model connector router |
| `EVAL_EMBEDDING_PROVIDER` / `EVAL_EMBEDDING_MODEL` | embed with another provider/model for one run (e.g. `openrouter` + `qwen/qwen3-embedding-8b`) |
| `SEARCH_LEXICAL_DRIVER` | the keyword channel of hybrid search: `pg_textsearch` or `postgres` |
| `EVAL_TOOL_POOL` | where the catalog is seeded: `preset` (default, shared catalog tools), `custom` (a custom MCP server's own tools) or `function` (webhook functions named `{connector}_{tool}`) — each exercises other search channels |

The default bars are per reranker model (`RerankModelCatalog::measured()`,
e.g. Cohere Rerank 4 Pro: read 0.30 / write 0.50 — the highest write bar with
zero starvation). The small-model router adds precision on top of the bar
(it cuts wrong-connector writes the bar cannot separate):

```bash
SCALEWAYAI_API_KEY=… AGENT_TOOL_CONNECTOR_CLASSIFIER=1 vendor/bin/pest --testsuite=Eval
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

# Knowledge retrieval eval

`KnowledgeRetrievalEvalTest.php` measures the agent's knowledge search
(`HybridChunkSearch` → relevance check → `KnowledgeBase::search`). Each run it
splits and embeds four documents for a fresh agent — the three chunking samples
in `resources/chunking/` and [`fixtures/knowledge/arabic.md`](fixtures/knowledge/arabic.md)
— then asks the labelled questions in
[`fixtures/knowledge/cases.php`](fixtures/knowledge/cases.php).

```bash
SCALEWAYAI_API_KEY=… vendor/bin/pest --testsuite=Eval --filter=knowledge
# with a reranker (Settings → AI); its key the same way, e.g. COHERE_API_KEY=…
```

| Env var | What it does |
|---|---|
| `VECTOR_SEARCH_RELEVANCE_THRESHOLD` | the relevance bar of this run |
| `VECTOR_SEARCH_MAX_CHUNKS` | how many chunks the model gets |
| `EVAL_ENRICH` | `1` = add a summary and questions to every chunk first (needs a chat model) |
| `EVAL_RERANKER` | the reranker driver of this run; `none` measures an install without one (the similarity check instead) |
| `EVAL_SIMILARITY_THRESHOLD` | the similarity bar without a reranker; only Qwen3 on Scaleway ships one (0.35), so give it when the same model runs through OpenRouter |
| `EVAL_CHAT_MODEL` / `EVAL_CHAT_PROVIDER` | the chat model for the app's own tasks (enhancement, follow-up rewrites) for one run, e.g. `google/gemma-4-26b-a4b-it` on `openrouter` (the default provider). Unset, the testing catalog's Scaleway model runs, and without a Scaleway key the follow-up rewrite quietly keeps the question as it is |
| `EVAL_CHUNK_SIZE` | the chunk size of this run, in the configured unit (tokens by default) |
| `EVAL_CHUNK_MINIMUM` | the smallest chunk of this run, same unit; `0` turns it off |
| `EVAL_RERANK_URL` / `EVAL_RERANK_MODEL` / `EVAL_RERANK_KEY` | rerank with any server that has the common `/rerank` API for one run (e.g. `https://api.jina.ai/v1` + `jina-reranker-v2-base-multilingual`) |

## Cases

```php
['query' => 'How long does express delivery take?', 'expect' => '1 to 2 working days'],
['query' => 'hi', 'expect' => null],                         // not covered: nothing should come back
['query' => 'and how much is it?', 'history' => ['How long does express delivery take?'], 'expect' => '9.95'],
```

Follow-ups go through the follow-up rewriter (Settings → Search), so its
effect shows in the `Follow-ups` line. [`fixtures/knowledge/qa.php`](fixtures/knowledge/qa.php)
holds saved Q&A answers and messages that should (or should not) get them by
meaning; its sweep picks the Q&A similarity bar.

Multi-hop and global questions list several facts, and need every one:

```php
['query' => 'Which shop can I leave a product for repair at, and when is that shop open?',
 'kind' => 'multi-hop', 'expect' => ['next to the workshop', 'Monday to Saturday, 9:00 to 18:00']],
['query' => 'Give me an overview of all the fees and costs I might pay.', 'kind' => 'global',
 'expect' => ['4.95, free from 60', 'the return costs 7.95', ['New zip on a jacket | 25', 'new zip on a jacket costs 25']]],
```

A multi-hop question joins facts from different chunks; a global one asks about
the whole collection, so its facts are spread over more chunks than the model
gets. A fact given as a list matches any of its wordings. These questions are
the baseline for retrieval that follows links between chunks (a knowledge graph).

Baseline, 2026-10-24 (OpenRouter: `qwen/qwen3-embedding-8b`, `cohere/rerank-4-pro`
at 0.7, 5 chunks to the model, 400-token chunks, 14 chunks in all):

| | facts top 5 | facts top 10 | facts given | every fact given |
|---|---|---|---|---|
| Multi-hop (11) | 100.0% | 100.0% | 86.4% | 72.7% |
| Global (7) | 84.4% | 93.8% | 81.2% | 28.6% |
| Multi-hop, enriched | 90.9% | 90.9% | 86.4% | 72.7% |
| Global, enriched | 84.4% | 90.6% | 81.2% | 28.6% |

"Enriched" is `EVAL_ENRICH=1` with `EVAL_CHAT_MODEL=google/gemma-4-26b-a4b-it`,
as an install with the default enhancement has it. What the model gets is the
same either way. Enhancement makes the hybrid ranking before the check worse
(single-fact hit@5 97.3% → 73.0%, MRR 0.886 → 0.693), and the reranker hides
it by lifting the right chunks back from ranks 6 to 13. With a real rewrite,
follow-ups go from 75% to 100% hit@5; the first run had no working rewrite
model, so the rewrite kept each follow-up as it was.

Without a reranker (`EVAL_RERANKER=none`, similarity bar 0.35 as Qwen3 on
Scaleway has it, `EVAL_SIMILARITY_THRESHOLD=0.35`), same day:

| | Enhancement off | Enhancement on |
|---|---|---|
| Single-fact hit@5 / MRR before the check | 97.3% / 0.872 | 78.4% / 0.727 |
| Single-fact answered after the check | 94.6% | 91.9% |
| Uncovered left empty | 100.0% | 100.0% |
| Multi-hop: facts / every fact given | 100.0% / 100.0% | 90.9% / 81.8% |
| Global: facts / every fact given | 71.9% / 14.3% | 68.8% / 14.3% |

Without a reranker nothing lifts the weaker ranking back, so enhancement
costs answers. The similarity bar also keeps every multi-hop fact the
reranker's 0.7 bar drops, and blocks every uncovered question (the reranker
blocked 57.1%), but it drops broad questions whose chunks each match only a
little ("all the fees" got nothing).

Every multi-hop fact the model missed ranked in the top 5: the relevance check
dropped it, not the search. With only 14 chunks the top 10 is most of the
collection, so treat these numbers as optimistic until the set grows.

`expect` is text that must be in a retrieved chunk (case-insensitive). Add real,
anonymized questions from your logs; keep some that the knowledge does **not**
cover, or the threshold sweep can only say "keep everything".

## Reading the scoreboard

| Line | Meaning |
|---|---|
| `Before the check` | the hybrid search's ranking: is the right passage in the top 1 / 3 / 5? `MRR` rewards it being first |
| `After the check` | what the model actually gets: questions that got their passage, and uncovered questions that got **nothing** |
| `Follow-ups` | questions that only make sense with the history |
| `Multi-hop` / `Global` | facts ranked in the top 5 / 10 and given to the model, and questions that got **every** fact; the facts the model missed are listed below |
| scores + sweep | the check's score for the right passage vs. the best score of an uncovered question, and for each threshold how many of each it keeps or blocks |

The best threshold keeps nearly every right passage and blocks most uncovered
questions. A reranker's scores have their own scale, so each reranker gets its
own threshold.

# Document reader eval

`DocumentReaderEvalTest.php` compares the built-in PHP readers
(`Domain\Knowledge\Document\Conversion\Markdown`) with the program each one
stands in for: pandoc for DOCX, ODT, RTF, EPUB and FB2, catdoc for DOC and
catppt for PPT. It needs a folder of sample documents and pandoc, and skips
without them.

```bash
EVAL_DOCUMENTS=/path/to/samples vendor/bin/pest --testsuite=Eval --filter=readers
```

| Env var | What it does |
|---|---|
| `EVAL_DOCUMENTS` | the sample folder, read recursively (required) |
| `EVAL_REPORT` | where the Markdown report goes (default: the temp folder) |
| `EVAL_OUTPUT` | a folder for each file's two Markdown readings, to diff by hand |
| `EVAL_CATDOC`, `EVAL_CATPPT` | the catdoc and catppt to compare with, when they are not the ones in the settings |
| `EVAL_RETRIEVAL` | `1` = also chunk and embed both readings and ask the questions in `EVAL_DOCUMENTS/questions.php` (needs a real embeddings key) |

For each file the report gives how many of the program's words the PHP reader
kept (recall) and added (precision), whether their order holds, and the
headings, tables, rows and footnotes on each side.
