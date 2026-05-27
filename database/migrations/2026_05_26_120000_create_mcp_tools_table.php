<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // First-class storage for MCP tool definitions discovered via
        // `tools/list` on each connected server. Two retrieval signals live
        // here side-by-side:
        //   - `embedding`     → Qwen3 dense vector, for semantic search.
        //   - `embedding_bm25` → BM25-tokenized (llmlingua2), for lexical
        //                        match on exact terms the user types.
        // SemanticToolRouter fuses both via Reciprocal Rank Fusion in SQL,
        // so a query like "render a heatmap" still finds the chart tool
        // even when GitHub vocabulary dominates the dense-vector signal.
        Schema::create('mcp_tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mcp_server_id')
                ->constrained('mcp_servers')
                ->cascadeOnDelete();

            // Unprefixed name as returned by the MCP server. The
            // `mcp__{slug}__` prefix is reapplied at compile time so the
            // stored name survives slug renames.
            $table->string('name', 64);
            $table->text('description');
            $table->jsonb('input_schema');

            // Per-tool enable/disable lives here, scoped to the parent
            // server. Toggling this affects every agent attached to that
            // server (one server per user, so semantically "this user
            // doesn't want this tool exposed to their agents"). Defaults
            // true on catalog sync.
            $table->boolean('enabled')->default(true);

            // Dense + sparse vectors. Both populated by EmbedMcpToolsJob
            // from `name + description` after each catalog sync.
            $table->vector('embedding', 1024)->nullable();
            $table->bm25vector('embedding_bm25')->nullable();

            // Bumped on every catalog sync; rows whose fetched_at didn't
            // advance after a sync are tools the server no longer exposes
            // and get deleted.
            $table->timestamp('fetched_at');

            $table->timestamps();

            $table->unique(['mcp_server_id', 'name']);
        });

        // BM25 index — used by SemanticToolRouter's RRF query.
        DB::statement('CREATE INDEX mcp_tools_embedding_bm25 ON mcp_tools USING bm25 (embedding_bm25 bm25_catalog.bm25_ops)');

        // vchordrq index for fast approximate cosine on the dense embedding.
        // L2-ops matches the operator used elsewhere (chunks); cosine is
        // equivalent on L2-normalised vectors which is what Qwen3 returns.
        DB::statement('CREATE INDEX mcp_tools_embedding_vchordrq ON mcp_tools USING vchordrq (embedding vector_l2_ops)');

        DB::statement('ANALYZE mcp_tools');
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_tools');
    }
};
