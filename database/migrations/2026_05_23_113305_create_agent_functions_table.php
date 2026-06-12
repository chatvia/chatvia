<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('functions', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

            $table->string('internal_name', 64);
            $table->string('display_name')->nullable();
            $table->text('description');
            $table->jsonb('parameters_schema');

            $table->text('webhook_url');
            $table->enum('webhook_method', ['POST', 'GET', 'PATCH', 'DELETE'])->default('POST');
            $table->text('webhook_headers')->nullable();
            $table->text('webhook_secret');

            $table->enum('auth_type', ['none', 'bearer', 'header', 'hmac'])->default('none');
            $table->text('auth_config')->nullable();

            // Per-function human-in-the-loop switch. A function is a single
            // tool, so this is its per-tool toggle. Seeded from webhook_method
            // at create (GET → false, write verbs → true), then user-editable.
            $table->boolean('requires_approval')->default(true);

            $table->unsignedInteger('timeout_ms')->default(10000);
            $table->unsignedInteger('max_response_bytes')->default(65536);

            $table->boolean('is_enabled')->default(true);

            $table->vector('embedding', 1024)->nullable();
            $table->bm25vector('embedding_bm25')->nullable();

            $table->timestamp('last_invoked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('invocation_count')->default(0);

            $table->timestamps();

            $table->unique(['user_id', 'internal_name']);
        });

        DB::statement('CREATE INDEX functions_embedding_bm25 ON functions USING bm25 (embedding_bm25 bm25_catalog.bm25_ops)');

        DB::statement('CREATE INDEX functions_embedding_vchordrq ON functions USING vchordrq (embedding vector_l2_ops)');

        Schema::create('agent_function', function (Blueprint $table) {
            $table->foreignIdFor(Agent::class)->constrained()->cascadeOnDelete();
            $table->foreignId('function_id')
                ->constrained('functions')
                ->cascadeOnDelete();

            $table->unsignedInteger('invocation_count')->default(0);
            $table->timestamp('last_invoked_at')->nullable();

            $table->primary(['agent_id', 'function_id']);

            $table->index('function_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_function');
        Schema::dropIfExists('functions');
    }
};
