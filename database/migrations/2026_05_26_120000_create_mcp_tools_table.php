<?php

use Domain\Agent\Models\Agent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('mcp_tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mcp_server_id')
                ->constrained('mcp_servers')
                ->cascadeOnDelete();

            $table->string('name', 64);
            $table->text('description');
            $table->jsonb('input_schema');

            // MCP tool annotations from `tools/list` (readOnlyHint,
            // destructiveHint, idempotentHint, openWorldHint). Untrusted hints
            // — used to derive `scope` and seed the per-tool `permission` default.
            $table->jsonb('annotations')->nullable();

            // Whether the tool reads or mutates state. Re-synced on every
            // catalog refresh from `annotations.readOnlyHint` (true → read,
            // missing/false → write). Drives the section split in the
            // Connectors UI ("Read-only" vs "Write/delete").
            $table->enum('scope', ['read', 'write'])->default('write');

            // Per-tool permission. Tri-state shown in the Connectors UI:
            //   - allow    → tool runs inline, no prompt
            //   - approval → tool pauses for the user via ToolApprovalCheckpoint
            //   - blocked  → tool is excluded from the catalog
            // Seeded from `annotations` on first catalog (readOnly → 'allow',
            // write/unknown → 'approval'), then user-toggleable.
            $table->enum('permission', ['allow', 'approval', 'blocked'])
                ->default('approval');

            $table->vector('embedding', 1024)->nullable();
            $table->bm25vector('embedding_bm25')->nullable();

            $table->timestamp('fetched_at');

            $table->timestamps();

            $table->unique(['mcp_server_id', 'name']);
        });

        DB::statement('CREATE INDEX mcp_tools_embedding_bm25 ON mcp_tools USING bm25 (embedding_bm25 bm25_catalog.bm25_ops)');

        DB::statement('CREATE INDEX mcp_tools_embedding_vchordrq ON mcp_tools USING vchordrq (embedding vector_l2_ops)');

        Schema::create('agent_mcp_tool', function (Blueprint $table) {
            $table->foreignIdFor(Agent::class)->constrained()->cascadeOnDelete();
            $table->foreignId('mcp_tool_id')
                ->constrained('mcp_tools')
                ->cascadeOnDelete();

            $table->unsignedInteger('invocation_count')->default(0);
            $table->timestamp('last_invoked_at')->nullable();

            $table->primary(['agent_id', 'mcp_tool_id']);
            $table->index('mcp_tool_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_mcp_tool');
        Schema::dropIfExists('mcp_tools');
    }
};
