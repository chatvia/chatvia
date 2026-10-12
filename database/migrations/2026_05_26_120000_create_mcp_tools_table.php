<?php

use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Embedding columns use the install's vector type and size (EmbeddingStorage).
        $ops = app(EmbeddingStorage::class)->type()->operatorClass();

        Schema::create('connector_tools', function (Blueprint $table) {
            $table->id();

            $table->string('connector_slug', 40);

            $table->string('name', 64);
            $table->text('description');
            $table->jsonb('input_schema');

            $table->jsonb('annotations')->nullable();

            $table->enum('scope', ['read', 'write'])->default('write');

            $table->enum('default_permission', ['allow', 'approval', 'blocked'])
                ->default('approval');

            $table->embedding('embedding')->nullable();
            $table->bm25vector('embedding_bm25')->nullable();

            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['connector_slug', 'name']);
        });

        DB::statement('CREATE INDEX connector_tools_embedding_bm25 ON connector_tools USING bm25 (embedding_bm25 bm25_catalog.bm25_ops)');
        DB::statement("CREATE INDEX connector_tools_embedding_vchordrq ON connector_tools USING vchordrq (embedding {$ops})");

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

            $table->enum('permission', ['allow', 'approval', 'blocked'])
                ->default('approval');

            $table->embedding('embedding')->nullable();
            $table->bm25vector('embedding_bm25')->nullable();

            $table->timestamp('fetched_at');

            $table->timestamps();

            $table->unique(['mcp_server_id', 'name']);
        });

        DB::statement('CREATE INDEX mcp_tools_embedding_bm25 ON mcp_tools USING bm25 (embedding_bm25 bm25_catalog.bm25_ops)');

        // No vector index: an agent reaches a few dozen MCP tools at most, so its
        // search ranks them by exact distance (an approximate index over every
        // workspace's rows would only add work and miss some).

        Schema::create('mcp_server_tool', function (Blueprint $table) {
            $table->foreignId('mcp_server_id')
                ->constrained('mcp_servers')
                ->cascadeOnDelete();
            $table->foreignId('connector_tool_id')
                ->constrained('connector_tools')
                ->cascadeOnDelete();

            $table->enum('permission', ['allow', 'approval', 'blocked'])->default('approval');

            $table->unsignedBigInteger('invocation_count')->default(0);
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
