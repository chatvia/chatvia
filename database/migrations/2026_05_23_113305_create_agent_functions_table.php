<?php

use App\Auth\Models\User;
use Domain\Agents\Models\Agent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Account-level webhook function definitions. Owned by the user
        // and attached to any number of their agents via the
        // `agent_function` pivot. Same model as `mcp_servers`.
        Schema::create('functions', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

            // What the LLM sees
            $table->string('name', 64);
            $table->text('description');
            $table->jsonb('parameters_schema');

            // Webhook target — encrypted at rest in the model casts.
            $table->text('webhook_url');
            $table->enum('webhook_method', ['POST', 'GET', 'PATCH', 'DELETE'])->default('POST');
            $table->text('webhook_headers')->nullable();
            $table->text('webhook_secret');                        // platform-generated
            // Auth modes + the matching `auth_config` JSON shape (encrypted
            // at rest). NULL when auth_type is `none`:
            //   bearer → { "token": "..." }            → `Authorization: Bearer <token>`
            //   header → { "name": "...", "value": "..." } → `<name>: <value>`
            //   hmac   → { "secret": "..." }           → body signed with HMAC-SHA256
            $table->enum('auth_type', ['none', 'bearer', 'header', 'hmac'])->default('none');
            $table->text('auth_config')->nullable();

            $table->unsignedSmallInteger('timeout_ms')->default(10000);
            $table->unsignedInteger('max_response_bytes')->default(65536);

            $table->boolean('enabled')->default(true)->index();

            // Dense + sparse vectors for hybrid retrieval. Populated by
            // EmbedFunctionJob from `name + description` after CRUD.
            // SemanticToolRouter fuses both channels via RRF in the same
            // SQL query that ranks MCP tools, so functions compete with
            // MCP tools on a single scoreboard.
            $table->vector('embedding', 1024)->nullable();
            $table->bm25vector('embedding_bm25')->nullable();

            // Observability
            $table->timestamp('last_invoked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('invocation_count')->default(0);

            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        // BM25 index — used by ToolHybridSearch's RRF query.
        DB::statement('CREATE INDEX functions_embedding_bm25 ON functions USING bm25 (embedding_bm25 bm25_catalog.bm25_ops)');

        // vchordrq index for fast approximate cosine on the dense embedding.
        DB::statement('CREATE INDEX functions_embedding_vchordrq ON functions USING vchordrq (embedding vector_l2_ops)');

        DB::statement('ANALYZE functions');

        // Pivot — which agents have this function attached. Pure junction:
        // presence of a row means "this agent can call this function."
        // To pause, detach. Conventional name: alphabetical singular of
        // the two related models.
        Schema::create('agent_function', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Agent::class)->constrained()->cascadeOnDelete();
            $table->foreignId('function_id')
                ->constrained('functions')
                ->cascadeOnDelete();

            $table->primary(['agent_id', 'function_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_function');
        Schema::dropIfExists('functions');
    }
};
