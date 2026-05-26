<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // First-class storage for MCP tool definitions discovered via
        // `tools/list` on each connected server. Previously these lived
        // only in a 6h Redis cache; promoting to rows gives us a clean
        // home for embeddings, queryability, and per-server tool
        // enable/disable.
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

            // Inline embedding (Qwen3 = 1024 dims). Stored as native
            // pgvector for storage-size parity with `chunks` and to keep
            // the option of SQL-side similarity if the pool grows.
            // Scoring still happens in PHP today (see SemanticToolRouter)
            // because per-agent pools are small and the registry caches.
            // Populated by EmbedMcpToolsJob after each catalog sync.
            $table->vector('embedding', 1024)->nullable();
            $table->timestamp('embedded_at')->nullable();

            // Bumped on every catalog sync; rows whose fetched_at didn't
            // advance after a sync are tools the server no longer exposes
            // and get deleted.
            $table->timestamp('fetched_at');

            $table->timestamps();

            $table->unique(['mcp_server_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_tools');
    }
};
