<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Central, workspace-agnostic catalog of every PRESET ("GA") connector's
        // tools — keyed by connector_slug, so leads/stripe/github/… are stored
        // ONCE no matter how many workspaces connect them. The heavy columns
        // (definition + embeddings) live here, single-copy; the per-workspace
        // permission lives on the mcp_server_tool pivot below. Custom servers
        // (connector_slug IS NULL) do NOT use this table — they keep their own
        // per-server rows in mcp_tools.
        Schema::create('connector_tools', function (Blueprint $table) {
            $table->id();

            // config/connectors.php key or a builtin slug (config/mcp.php). Never
            // null — null-slug (custom) servers use mcp_tools instead.
            $table->string('connector_slug', 40);

            $table->string('name', 64);            // raw, unprefixed MCP tool name
            $table->text('description');
            $table->jsonb('input_schema');

            // MCP tool annotations from `tools/list` (readOnlyHint, …). Untrusted —
            // used to derive `scope` and seed `default_permission`.
            $table->jsonb('annotations')->nullable();

            // Whether the tool reads or mutates state. Re-synced every refresh.
            $table->enum('scope', ['read', 'write'])->default('write');

            // The permission a freshly-connected workspace inherits for this tool:
            //   read → allow, write → approval (set explicitly on every sync).
            // Seed only — the live, user-toggleable value lives on mcp_server_tool.
            $table->enum('default_permission', ['allow', 'approval', 'blocked'])
                ->default('approval');

            // Shared embeddings — computed ONCE per (connector_slug, name).
            // Deliberately full fp32 vector (not halfvec like chunks): these tool
            // catalogs are tiny (tens of rows), so the storage win doesn't matter,
            // and ToolHybridSearch hard-codes `<-> ?::vector(1024)` with a
            // vector_l2_ops vchordrq index — keeping fp32 avoids a type/ops-class
            // mismatch that would silently drop the index.
            $table->vector('embedding', 1024)->nullable();
            $table->bm25vector('embedding_bm25')->nullable();

            $table->timestamp('fetched_at');
            $table->timestamps();

            // The de-dup key — also the concurrency guard for upserts when two
            // workspaces connect the same connector at once.
            $table->unique(['connector_slug', 'name']);
        });

        // The vchordrq + BM25 indexes now live on the shared catalog (one set of
        // index entries per connector tool, not one per workspace).
        DB::statement('CREATE INDEX connector_tools_embedding_bm25 ON connector_tools USING bm25 (embedding_bm25 bm25_catalog.bm25_ops)');
        DB::statement('CREATE INDEX connector_tools_embedding_vchordrq ON connector_tools USING vchordrq (embedding vector_l2_ops)');

        // Per-server tool catalog for CUSTOM MCP servers only (connector_slug
        // IS NULL). Preset/builtin servers route their tools through
        // connector_tools + mcp_server_tool instead.
        Schema::create('mcp_tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mcp_server_id')
                ->constrained('mcp_servers')
                ->cascadeOnDelete();

            $table->string('name', 64);
            $table->text('description');
            $table->jsonb('input_schema');

            $table->jsonb('annotations')->nullable();

            $table->enum('scope', ['read', 'write'])->default('write');

            // Per-tool permission. Tri-state shown in the Connectors UI:
            //   - allow    → tool runs inline, no prompt
            //   - approval → tool pauses for the user via ToolApprovalCheckpoint
            //   - blocked  → tool is excluded from the catalog
            $table->enum('permission', ['allow', 'approval', 'blocked'])
                ->default('approval');

            // Full fp32 vector (not halfvec) — see the note on connector_tools
            // above: small catalog + ToolHybridSearch's hard-coded
            // `<-> ?::vector(1024)` / vector_l2_ops index require fp32.
            $table->vector('embedding', 1024)->nullable();
            $table->bm25vector('embedding_bm25')->nullable();

            $table->timestamp('fetched_at');

            $table->timestamps();

            $table->unique(['mcp_server_id', 'name']);
        });

        DB::statement('CREATE INDEX mcp_tools_embedding_bm25 ON mcp_tools USING bm25 (embedding_bm25 bm25_catalog.bm25_ops)');

        DB::statement('CREATE INDEX mcp_tools_embedding_vchordrq ON mcp_tools USING vchordrq (embedding vector_l2_ops)');

        // Per-workspace membership: which catalog tools a given server advertises,
        // plus that workspace's user-toggleable permission and live invocation
        // counters. No heavy columns — those live on connector_tools. One row per
        // (server, catalog tool).
        Schema::create('mcp_server_tool', function (Blueprint $table) {
            $table->foreignId('mcp_server_id')
                ->constrained('mcp_servers')
                ->cascadeOnDelete();
            $table->foreignId('connector_tool_id')
                ->constrained('connector_tools')
                ->cascadeOnDelete();

            // Seeded from connector_tools.default_permission on first membership;
            // never clobbered on re-sync — this is the user's choice.
            $table->enum('permission', ['allow', 'approval', 'blocked'])->default('approval');

            $table->unsignedInteger('invocation_count')->default(0);
            $table->timestamp('last_invoked_at')->nullable();

            $table->primary(['mcp_server_id', 'connector_tool_id']);
            $table->index('connector_tool_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_server_tool');
        Schema::dropIfExists('mcp_tools');
        Schema::dropIfExists('connector_tools');
    }
};
