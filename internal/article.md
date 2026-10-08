# Building a Full AI Agents Platform in Laravel and Vue.js

*How I built Chatvia — a production RAG chat platform — on Larave, and the hard problems I hit along the way: chunking without LangChain, vector search that actually scales, fp16 embeddings, PDF ingestion, and token-by-token streaming.*

## What I set out to build

Chatvia is a platform for building AI agents grounded in your own content. You sign up, attach your material (PDFs, web pages, docs, Q&A pairs), and get an agent that can answer questions about that material — surfaced through a chat widget, a hosted chat page, a command bar, or a messaging channel like Telegram or Slack. It's general-purpose by design: customer support is one obvious use case, but the same engine powers an internal knowledge assistant over company docs, a research companion over a paper library, a documentation Q&A bot, a sales-enablement agent, or anything else that boils down to "answer from these documents, and optionally take actions." I tried hard not to bake "support" into the product, it's a RAG agent platform, and support is just one shape it takes.

Under the hood it's a Retrieval-Augmented Generation (RAG) system: the agent doesn't "know" anything, it retrieves the most relevant slices of your content and answers from them.

That sentence is easy to write and brutal to ship. RAG is a pipeline, and every stage of that pipeline has a failure mode that quietly degrades answers: bad chunking gives you fragments with no context, a weak retriever returns the wrong passage, fp32 vectors blow up your storage, a naive `ORDER BY embedding <-> query` brute-forces your whole table, and a slow stream makes the product feel broken even when the answer is perfect.

This article is the honest version of how I solved each of those in Laravel and Vue. The PHP part matters, because the entire AI tooling ecosystem assumes you're in Python, and that assumption broke me early.

---

## The stack and why

- **Laravel 13** (PHP 8.5) — the application core.
- **Inertia + Vue 3** — the dashboard SPA, server-driven routing without a separate API for the first-party UI.
- **Laravel Reverb** — first-party WebSockets for live progress (ingestion, crawling).
- **PostgreSQL + VectorChord** — the single source of truth *and* the vector database. No separate Pinecone/Weaviate/Qdrant.
- **Laravel Horizon** — Redis-backed queues for the heavy async work (ingestion, embedding, crawling).
- 
- Open-weight models throughout: **Qwen3-Embedding** for vectors, **Qwen3-Reranker** for the final ranking, and a Qwen3-family chat model for generation, served from EU infrastructure.

The deliberate choice here is **"Postgres is the database."** A huge amount of RAG tutorial content pushes you toward a bespoke vector store sitting next to your relational DB, and then you spend the rest of your life keeping two systems consistent, paying for two systems, and writing glue to join "the chunk vector over there" with "the document row over here." I did not want that. I wanted one database where a chunk's embedding, its BM25 vector, its tenant `user_id`, its source relationship, and its encrypted text all live in the same row and get deleted in the same cascade. That decision drove almost everything downstream.

---

## Architecture

Before any AI, a word on structure, because an AI platform accretes complexity faster than almost anything else — ingestion, embeddings, retrieval, tools, billing, channels, analytics — and without discipline it becomes a mud ball.

I split the codebase into three root namespaces:

- `App\` — the HTTP layer, framework wiring, controllers, middleware, form requests.
- `Domain\` — business logic: Actions, DTOs, Models, Services, Events.
- `Support\` — small framework-agnostic helpers.

Every state-changing request flows the same way:

```
HTTP Request
  → Route → Controller
      → FormRequest (validate + authorize) → ->toData() → DTO
      → Action::execute(DTO, ...domain models) → domain object | void
      → (optional) Job::dispatch(DTO) — Job::handle() calls the same Action
  → Controller returns Response (Inertia / JSON / Redirect)
```

The rules that keep it honest:

- **Actions** have exactly one public method, `execute()`. They never see a `Request`, never touch `Auth`, never return an HTTP response. They take DTOs and domain models, do the work, return a domain object or `void`.
- **Jobs hold no logic.** A job takes a DTO in its constructor and calls an Action's `execute()` in `handle()`. That means the *exact same* ingestion logic runs whether it was triggered synchronously in a test or off the queue in production.
- **Services** wrap things the app *uses* (Cohere, Mistral OCR, chunk.fit, LibreOffice). Actions are things the app *does*. The litmus test — "does this represent something my app does or something it uses?" — decides where code lands every time.

This matters for AI work specifically because ingestion is a long chain of fallible external calls (OCR → chunk → enhance → embed → index). Modeling each step as an Action invoked by a Job gives me retries, isolation, and unit tests for each link without standing up the whole pipeline.

---

## The first wall: there is no reliable text splitter for PHP

Here's the problem that shaped the whole project.

In RAG, you can't embed a whole document. Embedding models have a token limit, and more importantly, a single vector for a 40-page PDF is useless — it averages everything into mush. You have to **chunk**: split the document into coherent, retrievable, appropriately-sized pieces, embed each one, and retrieve at the chunk level.

Good chunking is deceptively hard. Naive approaches all fail in characteristic ways:

- **Split every N characters** → cuts sentences in half, splits a table from its header, separates a definition from the term it defines.
- **Split on every newline / every Markdown heading** → on a clause-heavy page (terms of service, refund policy, pricing tiers) you get dozens of sub-100-token fragments, each too small to carry meaning. Retrieval returns "Section 4.2." with no Section 4.2 content.
- **Split on sentences only** → better, but a single sentence is rarely a good retrieval unit; you want token-budgeted groups of sentences with overlap.

The Python ecosystem solved this years ago. **LangChain** has `RecursiveCharacterTextSplitter`, `MarkdownHeaderTextSplitter`, token-aware splitters. **LlamaIndex** has `SentenceSplitter`, `SemanticSplitterNodeParser`. **Unstructured** has `chunk_by_title`, which partitions a document into title-led sections and then *intelligently combines* small adjacent sections up to a size budget — exactly what clause-heavy pages need.

In **PHP, none of this exists.** I went looking and found a handful of abandoned packages that did character-count splitting and called it a day. There is no maintained, battle-tested, tokenizer-aware sentence/semantic splitter for PHP. The good tokenizers (tiktoken's `cl100k_base`, the model-specific ones) are Python/Rust. The good partitioners are Python. This is not a small gap — chunk quality is one of the two or three biggest levers on answer quality in the entire system, and PHP simply doesn't have the tools.

I had three options:

1. **Port LangChain/LlamaIndex splitters to PHP.** Months of work to chase a moving target, and I'd be reimplementing tokenizers I don't fully trust.
2. **Rewrite the ingestion pipeline in Python** as a separate service. A whole second runtime, second deploy, second language in the codebase, for one job.
3. **Build a small, focused HTTP service that exposes exactly the Python splitters I need**, and call it from PHP like any other API.

I chose option 3.

---

## Building chunk.fit: a chunking service for the PHP world

[chunk.fit](https://chunk.fit) is a small service I built to expose the best Python chunking libraries — LlamaIndex and Unstructured — behind a clean HTTP API, so a PHP (or any) backend can get production-grade chunking without hosting a Python runtime itself.

The idea is narrow on purpose: it does one thing, splitting text, and does it with the libraries that are actually good at it. From Chatvia's side it's just a typed client (`ChunkFitClient`) with two methods that map to the two strategies I use.

**Sentence splitting** (LlamaIndex `SentenceSplitter`), token-budgeted in the same `cl100k_base` currency I use everywhere else in the pipeline:

```php
public function splitSentences(string $text, int $chunkSize, int $chunkOverlap = 0): array
{
    $response = $this->request(
        endpoint: '/llama-index/splitters/sentence',
        body: [
            'text' => $text,
            'config' => [
                'chunk_size'    => $chunkSize,
                'chunk_overlap' => $chunkOverlap,
                'tokenizer'     => $this->tokenizer(), // cl100k_base
            ],
        ],
    );

    return $this->extractChunks($response);
}
```

**Title-aware splitting** (Unstructured `chunk_by_title`) for web content, where the header-splitter shredding problem is worst:

```php
/**
 * Unlike the raw Markdown header splitter (which starts a new chunk at every
 * heading and never merges, shredding clause-heavy pages into dozens of
 * sub-100-token fragments), this partitions the text into title-led sections
 * and then COMBINES small adjacent sections up to combine_text_under_n_chars
 * while capping size at max_characters. The result is a small set of coherent,
 * evenly-sized, fragment-free chunks.
 */
public function splitByTitle(string $text): array
{
    $response = $this->request(
        endpoint: '/unstructured/splitters/text',
        body: [
            'text' => $text,
            'config' => [
                'max_characters'             => $this->maxCharacters(),      // 2000
                'new_after_n_chars'          => $this->newAfterNChars(),     // 1500
                'combine_text_under_n_chars' => $this->combineUnderNChars(), // 1000
                'overlap'                    => 0,
            ],
        ],
    );

    return $this->extractChunks($response);
}
```

The client is defensive: it requires an API key, sets a timeout, retries twice with backoff, and throws a typed `ChunkFitException` on any non-2xx so the calling Action can decide whether to fall back or fail the ingestion cleanly.

### Letting the LLM do the semantic work

For PDFs I go one step further than mechanical splitting. After OCR gives me clean per-page text, I run each page through an LLM-driven chunker that returns a **strict JSON schema** — not free text, not XML tags — describing the page:

```php
'response_format' => [
    'type' => 'json_schema',
    'json_schema' => [
        'name' => 'document_chunk',
        'strict' => true,
        'schema' => [
            'type' => 'object',
            'properties' => [
                'summary'   => ['type' => 'string',  /* 2-5 sentence natural-language summary */ ],
                'questions' => ['type' => 'array', 'items' => ['type' => 'string'],
                                'minItems' => 5, 'maxItems' => 7 /* questions this page answers */ ],
            ],
            'required' => ['summary', 'questions'],
            'additionalProperties' => false,
        ],
    ],
],
```

Two design decisions worth calling out:

1. **The page is the chunk; the summary and questions are *retrieval aids*.** I keep the original page text as the chunk (so the model answers from the real content), but I *also* embed an LLM-generated summary and a set of 5–7 questions the page answers. This is the "hypothetical questions" technique — users phrase queries as questions, and matching a question-to-question is often far more accurate than matching a question to a dense paragraph. Those questions feed two of the four retrieval channels (more below).

2. **`strict: true` JSON schema, not a tag protocol.** My first version asked the model to wrap chunks in `<Chunk>...</Chunk>` tags. It worked until a model dropped a closing tag, or wrapped the whole thing in a Markdown fence, and a chunk silently vanished. A strict JSON schema makes malformed output impossible — the model provider enforces the shape — so I never lose a chunk to a truncated tag again.

---

## PDF and document processing

PDFs are where most RAG systems quietly fall apart, because "a PDF" is not one thing. It's a born-digital invoice with a perfect text layer; or a scanned contract that's really just images of text; or a slide deck; or a spreadsheet exported to PDF with a table that means nothing once linearized.

So I don't have *a* PDF parser, I have a routed pipeline:

- **Born-digital, clean text layer** → extract directly. Fast and free.
- **Scanned / image-based** → OCR. I use **Mistral OCR** (`MistralOCR` client) as the primary, which returns Markdown-structured page content rather than a flat character soup, and **Google Document AI** and **LlamaParse** are available as alternate routes for layout-heavy documents.
- **Office formats** (`.docx`, `.pptx`, `.xlsx`, `.odt`, …) → convert first. I shell out to **LibreOffice** (headless) and **Pandoc** to normalize office documents into something I can OCR or extract cleanly, rather than trying to parse a dozen binary formats in PHP.

OCR gives me `PageData` objects — one per page, with Markdown content. From there:

1. Each page goes to the **Chunker**, which (as above) keeps the page as the chunk and generates a summary + questions, passing the previous and next page as context so the model understands where a page sits in the document:

   ```php
   public function chunkDocument(PageData $page, ?PageData $prevPage = null, ?PageData $nextPage = null): DocumentChunk
   ```

2. Chunks are **deduplicated by checksum** before embedding — scanned documents are full of near-identical headers/footers and blank pages, and there's no point embedding the same boilerplate fifty times. (This dedup later caused a fun bug: per-chunk progress can't naturally reach 100% when checksum dedup drops pages, so the UI would hang at 97% "ingesting." The fix was to broadcast a terminal "done" event from the batch's `then()` callback rather than relying on the per-chunk counter.)

3. Surviving chunks are embedded and indexed.

### PDFs in the chat itself

Separately from ingestion, a user can drop a PDF *into a conversation* and ask about it. That path is latency-sensitive and streams, so it has its own `MistralPdfSource` stream source and guardrails: a `MaxPdfPages` validation rule and a `HasMessageCreditsForPdf` rule so a 400-page upload can't blow through someone's plan in a single message. Ingestion-grade processing and chat-grade processing are deliberately different code paths with different trade-offs.

---

## Embeddings, and why I store them as halfvec?

Every chunk gets embedded with **Qwen3-Embedding** into a **1024-dimensional** vector. Multiply that out: the `chunks` table is the largest table in the system, and it carries up to **four** vectors per row — a dense embedding of the chunk, a dense embedding of its questions, and two corresponding BM25 sparse vectors.

A `vector(1024)` in pgvector is fp32: 4 bytes × 1024 = **4 KB per vector**. With two dense vectors per row that's 8 KB of vector data per chunk before you count the BM25 vectors, the text, and the indexes. Across millions of chunks that's the dominant cost in the database — heap size, index size, RAM for the index, backup size.

So I store the dense embeddings as **`halfvec`** (fp16) instead of `vector` (fp32):

```php
$table->halfvec('embedding', 1024);
$table->bm25vector('embedding_bm25');

$table->halfvec('questions_embedding', 1024)->nullable();
$table->bm25vector('questions_embedding_bm25')->nullable();
```

Why fp16 is safe here, specifically:

- **The embeddings are unit-normalized** API outputs. fp16 has ~3 decimal digits of precision, which is plenty to preserve the *ranking* of cosine/L2 distances for approximate nearest-neighbor search. ANN is approximate by definition — I'm not doing exact arithmetic, I'm ordering candidates.
- **A reranker re-scores the finalists from the original text**, not from the vector. So even if fp16 nudges the candidate ordering slightly, the precise relevance decision is made downstream by a cross-encoder reading the actual words. The vector's only job is to get the right ~12 candidates into the room; the reranker picks the winners.
- **It halves the storage and index volume of the largest table.** That's not a micro-optimization — it's the difference between the vector indexes fitting in RAM or not, which is the difference between fast search and disk-bound search.

If I were doing exact retrieval with no reranker, I'd think harder about fp32. With ANN + rerank, fp16 is free quality and a huge cost win. Anytime you do capacity math on this system, **never assume fp32** — the vectors are 2 bytes per dimension, not 4.

---

## Why VectorChord, not "pgvector + a bm25 extension"

This is the question I get asked most, so let me be precise about it. The choice is not "pgvector vs. something else" — VectorChord *is* part of the pgvector ecosystem. The choice is about **what runs the search**.

[pgvector](https://github.com/pgvector/pgvector) is excellent and I still depend on it (the `pgvector/pgvector` PHP package handles type marshalling). But pgvector's own index, HNSW, has real limits at scale:

- **Build time and memory.** HNSW indexes are expensive to build and hold a lot in memory. Rebuilding after a big ingestion burst is painful.
- **It's a pure-vector index.** It does ANN and nothing else. Keyword search is a separate world.

[**VectorChord**](https://github.com/tensorchord/VectorChord) (the `vchord` extension, with its `vchordrq` index) is built by the TensorChord team as a more scalable successor to `pgvecto.rs`. What it buys me:

- **`vchordrq` — RaBitQ residual quantization.** Instead of HNSW's graph, it uses an IVF-style partitioning with quantized residuals. It builds faster, uses far less memory, and scales to tens of millions of vectors on a single Postgres box. It's designed for exactly my situation: large tables, frequent bulk ingestion, single-node Postgres.
- **A `prefilter` mode that respects my tenant qualifier.** This is the part that actually matters for a multi-tenant SaaS and that naive setups get catastrophically wrong. More on that in the next section.
- **It works *with* halfvec.** My indexes are `vchordrq (embedding halfvec_l2_ops)` — quantized fp16 vectors, the smallest possible footprint.

For **keyword search** I add **`vchord_bm25`** (the `bm25vector` type and `bm25` index) plus **`pg_tokenizer`** with the **`llmlingua2`** tokenizer. This is real BM25 — proper term-frequency / inverse-document-frequency scoring — running *inside Postgres*, on the same rows, in the same query, under the same transaction as the vector search. Not Postgres's built-in `tsvector` full-text (which is fine but not BM25), and not a separate Elasticsearch cluster I'd have to keep in sync.

The whole thing installs as a set of extensions in one migration:

```php
$extensions = [
    'pg_stat_statements', 'pg_trgm', 'btree_gin', 'btree_gist',
    'vector',        // pgvector — types + the halfvec type
    'vchord',        // VectorChord — vchordrq index (RaBitQ)
    'pg_tokenizer',  // tokenizers, incl. llmlingua2
    'vchord_bm25',   // BM25 scoring inside Postgres
];

foreach ($extensions as $extension) {
    DB::statement("CREATE EXTENSION IF NOT EXISTS {$extension} CASCADE");
}

// llmlingua2 tokenizer for BM25
DB::statement("PERFORM tokenizer_catalog.create_tokenizer('llmlingua2', 'model = \"llmlingua2\"')");

// THE important line — see below
DB::statement("ALTER DATABASE ... SET vchordrq.prefilter = on");
```

So the honest one-line answer to "why VectorChord and not pgvector + bm25": **I wanted dense ANN *and* BM25 *and* a correct multi-tenant prefilter, all inside one Postgres query, all on quantized fp16 vectors, with an index I can rebuild cheaply.** VectorChord + vchord_bm25 is the only combination I found that delivers all of that on a single node without a second datastore.

### The prefilter, and why it's the whole ballgame for multi-tenant RAG

Here's the trap. In a multi-tenant system, every search is scoped: agent X can only retrieve from agent X's sources, which belong to workspace Y. The naive query is:

```sql
SELECT ... FROM chunks
WHERE user_id = :workspace
  AND EXISTS (SELECT 1 FROM agent_sources s WHERE s.agent_id = :agent AND ...)
ORDER BY embedding <-> :query
LIMIT 4;
```

With a normal ANN index, the planner does one of two bad things: it either does the ANN scan first and *then* filters (so a small tenant's results get thrown away after the fact, destroying recall — you asked for the top 4 globally and none of them belong to this tenant), or it abandons the index and brute-forces. Either way, **your search cost and recall degrade as the *whole platform's* data grows**, not just the tenant's. That's a multi-tenant time bomb.

`vchordrq.prefilter = on` makes the index honor the `WHERE` predicate *during* the ANN traversal. Combined with a scalar, btree-indexed tenant qualifier (`c.user_id = ?`) carried in every channel, the planner gets a cheap narrowing path and the search cost scales with the *tenant's* data, not the platform's. I learned this the hard way: an earlier version routed everything through a `candidate_chunks` CTE, which presented the rankers with an opaque intermediate and bypassed every vector/BM25 index — leaving recall to brute force. Rewriting each channel as a self-contained subquery `FROM chunks c` with the filter inlined as `EXISTS (...)` is what let the prefilter actually kick in.

---

## Hybrid search: four channels fused with RRF

Dense vector search is great at "what does this *mean*" and bad at "find this exact product code / name / SKU." BM25 is the opposite. Real user queries need both. So retrieval in Chatvia is **hybrid**, and specifically it fuses **four** channels:

1. **Dense** — query vector vs. chunk embedding (semantic).
2. **Dense-on-questions** — query vector vs. the embedding of the LLM-generated *questions* (question-to-question matching).
3. **BM25** — query terms vs. chunk text (lexical/keyword).
4. **BM25-on-questions** — query terms vs. the questions.

Each channel independently ranks the chunks, and I fuse the rankings with **Reciprocal Rank Fusion (RRF, k=60)**. RRF is the right tool here because the four channels produce scores on completely different and incomparable scales — an L2 distance and a BM25 score can't be added. RRF throws the raw scores away and uses only the *rank position* in each channel: a chunk that ranks #1 in BM25 and #3 in dense gets `1/(60+1) + 1/(60+3)`, summed across the channels it appears in. Robust, scale-free, no normalization tuning.

The whole thing is one SQL statement built in a query builder. Each channel is a self-contained subquery so the `vchordrq` prefilter accelerates its `ORDER BY` through the index:

```sql
WITH vector_ranked AS (
    SELECT t.id, ROW_NUMBER() OVER (ORDER BY t.dist) AS rank, 'vector' AS search_type
    FROM (
        SELECT c.id, c.embedding <-> ?::halfvec(1024) AS dist
        FROM chunks c
        WHERE c.user_id = ?                          -- tenant qual (btree, prefilterable)
          AND EXISTS (SELECT 1 FROM agent_sources s  -- agent scope
                      WHERE s.agent_id = ?
                        AND s.sourceable_id = c.chunkable_id
                        AND s.sourceable_type = c.chunkable_type)
        ORDER BY dist
        LIMIT ?                                       -- candidate pool per channel
    ) t
),
question_ranked AS ( ... c.questions_embedding <-> ?::halfvec(1024) ... ),
bm25_ranked AS (
    SELECT t.id, ROW_NUMBER() OVER (ORDER BY t.dist) AS rank, 'bm25' AS search_type
    FROM (
        SELECT c.id, c.embedding_bm25 <&> bm25_catalog.to_bm25query(
                   'chunks_embedding_bm25',
                   tokenizer_catalog.tokenize(?, 'llmlingua2')
               ) AS dist
        FROM chunks c
        WHERE c.user_id = ? AND EXISTS (...)
        ORDER BY dist LIMIT ?
    ) t
),
question_bm25_ranked AS ( ... ),
combined_results AS (
    SELECT id, rank, search_type FROM vector_ranked
    UNION ALL SELECT id, rank, search_type FROM question_ranked
    UNION ALL SELECT id, rank, search_type FROM bm25_ranked
    UNION ALL SELECT id, rank, search_type FROM question_bm25_ranked
),
rrf_scores AS (
    SELECT id,
           STRING_AGG(DISTINCT search_type, '+') AS search_type,
           SUM(1.0 / (60 + rank)) AS rrf_score   -- Reciprocal Rank Fusion, k=60
    FROM combined_results
    GROUP BY id
)
SELECT c.*, r.search_type, r.rrf_score AS score
FROM rrf_scores r JOIN chunks c USING (id)
ORDER BY r.rrf_score DESC
LIMIT ?;
```

A few operational details that took iteration to get right:

- **`SET LOCAL vchordrq.probes` inside a transaction.** The number of IVF lists probed trades recall for latency. For `lists = 4096`, `probes = 64` (roughly `sqrt(lists)`) is the sweet spot. It has to be `SET LOCAL` inside a `DB::transaction`, because on pooled connections (pgbouncer, Octane) a plain `SET` would leak the setting across unrelated requests.
- **Cheap short-circuits.** Before paying for a query embedding and a four-channel scan, I check: does this agent have *any* sources at all? If not, return `[]` immediately — `agent_id` is the leading column of the `agent_sources` primary key, so it's a single index seek.
- **Chunk text is encrypted at rest.** The raw `DB::select` bypasses Eloquent's `encrypted` cast, so I decrypt the chunk text in PHP after the rows come back, before anything downstream (rerank, response assembly) reads it.
- **The morph type must be the alias, not the FQCN.** `chunkable_type` stores `getMorphClass()` (e.g. `source_file`), not `App\...\SourceFile::class`. Get this wrong and the `agent_sources ↔ chunks` join silently returns zero rows and the agent insists it has no information — a genuinely maddening bug because nothing errors.

---

## Reranking the last mile

The four-channel RRF gives me a strong **candidate pool** — by default 12 chunks. But the model only gets the top few (3 by default), and the difference between "the most relevant 3" and "3 pretty-relevant ones" is the difference between a precise answer and a vague one.

So the last step is a **cross-encoder reranker** (Qwen3-Reranker). Unlike the bi-encoder embeddings — which encode query and document *separately* and compare vectors — a cross-encoder reads the query and each candidate *together* and scores true relevance. It's far more accurate and far too slow to run over the whole table, which is exactly why it goes last: cheap-but-approximate hybrid search narrows millions of chunks to 12, then expensive-but-precise reranking picks the final 3.

```php
$candidateLimit = $rerankEnabled
    ? config('chatvia.vector.search.rerank_candidates', 12) // wide pool for the reranker
    : ($maxChunks * 2);

// ... hybrid search returns candidates ...

if (count($results) > 1 && $rerankEnabled && $provider->supportsReranking()) {
    $results = $this->rerank($query, $results, $provider);  // cross-encoder
} else {
    $results = $this->filterByDbConfidence($results);       // fall back to RRF score
}
```

The reranker's value is *promoting the right chunk into the final cut*, so the candidate pool has to be comfortably wider than the final count — if you rerank exactly as many candidates as you'll keep, it's a no-op reorder. I instrument every phase separately (`embed_ms`, `sql_ms`, `rerank_ms`) so when latency creeps up I can see whether it's a cold embedding call, the four-channel SQL, or the reranker round-trip, and tune the right knob.

The same hybrid-search-then-rerank machinery also powers **tool routing** — when an agent has dozens of tools, I embed the tool descriptions and retrieve the relevant ones per turn instead of stuffing all of them into the prompt. Same retrieval engine, different corpus.

---

## Streaming: SSE for the turn, WebSockets for the background

A chat product lives or dies on perceived latency. If the answer appears all at once after eight seconds, it feels broken even if eight seconds is genuinely fast for the work being done. Tokens have to *stream*. But there are actually two very different real-time problems here, and I solved them with two different transports.

### The turn itself: Server-Sent Events

While the model is generating a reply, I stream tokens to the browser over **SSE** (a one-way `text/event-stream` HTTP response). SSE is the right fit: it's one-directional (server → client), it rides ordinary HTTP, it auto-reconnects, and it doesn't need a WebSocket handshake or a separate server. A single `SseStreamer` is the only place in the app allowed to construct a `text/event-stream` response — it owns headers, output-buffer cleanup, flushing, sequence numbering, and client-disconnect detection.

The stream is an **envelope of typed events**, not a raw token firehose:

```php
enum EventType: string {
    case Start   = 'start';   // turn started
    case Delta   = 'delta';   // a token / text fragment
    case Meta    = 'meta';    // model, timing, usage
    case Sources = 'sources'; // the retrieved chunks that grounded this answer
    case Tool    = 'tool';    // a tool call is happening
    case Replace = 'replace'; // replace buffered text (e.g. after a guard rewrite)
    case Error   = 'error';
    case Done    = 'done';    // terminal
}
```

This typed envelope is what makes the UI rich rather than just a typewriter. The frontend can render "searching knowledge base…", then show *which sources* grounded the answer, then stream the answer tokens, then surface a tool call inline — all from one ordered event stream. A few hard-won details:

- **`set_time_limit(0)` for the stream.** A turn that calls a tool, renders a chart, and writes a follow-up can easily exceed PHP-FPM's 30-second default. The real termination signal is client-disconnect detection inside the emit loop, not the wall clock.
- **Aggressively tear down output buffering and flush after every event**, or the web server buffers your "stream" and delivers it in one lump — the classic "why isn't my SSE streaming" bug.
- **Request dedup.** A `RequestDedupeStore` guards against the browser double-firing a stream request (Vue StrictMode-style double effects, impatient retries), which would otherwise bill the user twice and produce two interleaved streams.
- **Source families.** Different reply sources (`LlmSource`, `MistralPdfSource`, `QASource`, `HandoffSource`, `ReplaySource`, `GuardBlockSource`) all implement the same generator interface, so the streamer doesn't care whether tokens come from the LLM, a PDF chat, a canned Q&A answer, or a replay of a stored conversation.

### The background: Reverb + Echo

Ingestion is the other real-time problem, and it's fundamentally different: it's slow (OCR a 60-page PDF, crawl a 500-page website, embed thousands of chunks), it happens on a **queue worker**, not in the request, and the user might navigate away and come back. SSE is wrong for this — there's no open HTTP request to stream into.

So ingestion progress rides **WebSockets via Laravel Reverb** and Echo. A queued job broadcasts `FileIngestionProgressed` / `WebsiteCrawlStarted` events, and the dashboard subscribes and updates a progress bar live, regardless of which page the user is on. The rule I settled on: **SSE for in-flight, stream-coupled state** (the tokens and tool calls *of this turn*); **Reverb/Echo for async background progress** (ingestion, crawling) that outlives any single request.

(And the gotcha I mentioned earlier: because checksum dedup drops blank/duplicate pages, per-chunk progress mathematically can't reach 100%. The completion has to be an explicit terminal broadcast fired from the batch's `then()` callback, or the bar hangs near the end forever.)

---

## Tools, MCP, and the agent loop

An answer-from-documents bot is useful; an agent that can *act* is a product. Chatvia agents can call tools — webhooks (FunctionTools), MCP servers (McpTools), and branded OAuth connectors (ConnectorTools like GitHub, Linear, Stripe, Notion). They all funnel through one shared `Tool` abstraction and run inside a tool-loop runner: the model proposes a tool call, the runner executes it, feeds the result back, and the loop continues until the model produces a final answer — all while streaming `tool` events to the UI so the user sees what's happening.

Two things keep this safe and scalable:

- **Tool routing via the same retrieval engine.** With dozens of tools available, I don't dump every schema into the prompt (expensive, and it degrades the model's tool-selection). I embed tool descriptions and hybrid-search the relevant ones per turn, gated by a per-scope relevance threshold (read tools at a lower bar than write tools, since a wrong write is worse than a wrong read).
- **An immutable audit trail and access policy.** Every tool call is logged immutably, gated by a `ToolAccessPolicy` and rate limits, and surfaced in a governance viewer — because the moment an AI can take actions on your behalf, "what did it do and was it allowed to" stops being optional.

I also expose *Chatvia's own* capabilities as MCP servers (charts, account analytics) using Laravel's first-party MCP package, so the same protocol the agent uses to reach GitHub is how it reaches internal tools. One abstraction, inside and out.

---

## Lessons learned

**The PHP AI gap is real, but it's an integration gap, not a wall.** The models, the embeddings, the rerankers are all just HTTP APIs — PHP talks to them fine. The thing that's genuinely missing is the *local* tooling: tokenizers, splitters, partitioners. The pragmatic answer wasn't to abandon PHP or fork the pipeline into Python; it was to put the one irreplaceable Python capability (chunking) behind a small HTTP boundary — chunk.fit — and keep everything else in the language my app is written in.

**Postgres can be your vector database, and you probably want it to be.** VectorChord + vchord_bm25 gave me dense ANN, real BM25, and a correct multi-tenant prefilter in a single query on a single node, with no second datastore to keep consistent. The operational simplicity of "it's all just rows in Postgres" — one backup, one cascade delete, one transaction — is worth an enormous amount.

**The prefilter is the multi-tenant detail nobody warns you about.** A vector search that scales with *total platform data* instead of *tenant data* is a landmine that only goes off once you have real customers. Get the index-honored `WHERE` predicate right early.

**fp16 is free quality when you rerank.** `halfvec` halved the footprint of my biggest table with no measurable hit to answer quality, because the reranker makes the precise call from text. Don't pay for fp32 precision your pipeline throws away.

**Chunking is upstream of everything.** I spent more time on chunk quality than on any model choice, and it paid back more. A perfect retriever over badly-chunked content still returns garbage. Get the pieces right — coherent, self-contained, appropriately sized, augmented with summaries and questions — and the rest of the pipeline has something good to work with.

**Two real-time problems, two transports.** SSE for the turn, WebSockets for the background. Trying to force one to do both jobs is how you end up with hung progress bars and un-streamable streams.

**Structure is a feature.** An AI platform grows tentacles fast — ingestion, retrieval, tools, billing, channels, analytics. The strict Action/DTO/Service layering is the only reason I can still reason about the thing. Every fallible external step is an Action behind a Job: testable in isolation, retryable in production, identical in both.

None of this is exotic. It's Laravel, Vue, Postgres, and a handful of HTTP APIs, assembled with care at each stage where RAG quietly fails. The hard part was never any single piece — it was refusing to let any one stage be the weak link.
