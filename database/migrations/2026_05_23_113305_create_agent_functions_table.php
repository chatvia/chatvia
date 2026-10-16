<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Retrieval\Actions\ConfigureLexicalIndexesAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Embedding columns use the install's vector type and size (EmbeddingStorage).

        Schema::create('functions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

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

            $table->boolean('requires_approval')->default(true);

            $table->unsignedInteger('timeout_ms')->default(10000);
            $table->unsignedInteger('max_response_bytes')->default(65536);

            $table->boolean('is_enabled')->default(true);

            $table->embedding('embedding')->nullable();
            // What the embedding was computed from; the lexical channels search it.
            $table->text('search_text')->nullable();

            $table->timestamp('last_invoked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('invocation_count')->default(0);

            $table->timestamps();

            $table->unique(['workspace_id', 'internal_name']);
        });

        app(ConfigureLexicalIndexesAction::class)->execute('functions');

        // No vector index: an agent reaches a few dozen functions at most, so its
        // search ranks them by exact distance (an approximate index over every
        // workspace's rows would only add work and miss some).

        Schema::create('agent_function', function (Blueprint $table): void {
            $table->foreignIdFor(Agent::class)->constrained()->cascadeOnDelete();
            $table->foreignId('function_id')
                ->constrained('functions')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('invocation_count')->default(0);
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
