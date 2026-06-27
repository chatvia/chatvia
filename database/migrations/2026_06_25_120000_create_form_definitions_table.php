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

        Schema::create('form_definitions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignIdFor(Agent::class)->constrained()->cascadeOnDelete();

            $table->string('name');
            // Visitor-facing blurb shown under the form title in the chat.
            $table->string('subtitle', 500)->nullable();
            $table->string('slug', 64);
            $table->enum('form_type', ['lead', 'booking', 'qualification', 'generic'])->default('lead');

            $table->jsonb('schema');

            $table->jsonb('lead_field_map')->nullable();

            $table->string('success_message')->nullable();

            // The agent's "when to show this form" trigger instruction — embedded
            // for intent retrieval, never shown to the visitor.
            $table->text('agent_instructions')->nullable();
            $table->boolean('is_active')->default(true);

            $table->vector('embedding', 1024)->nullable();
            $table->bm25vector('embedding_bm25')->nullable();

            $table->timestamps();

            $table->unique(['agent_id', 'slug']);
        });

        DB::statement('CREATE INDEX form_definitions_embedding_bm25 ON form_definitions USING bm25 (embedding_bm25 bm25_catalog.bm25_ops)');
        DB::statement('CREATE INDEX form_definitions_embedding_vchordrq ON form_definitions USING vchordrq (embedding vector_l2_ops)');

        Schema::create('form_submissions', function (Blueprint $table): void {
            $table->id();

            $table->uuid('external_id')->unique();

            $table->foreignId('form_definition_id')->constrained('form_definitions')->cascadeOnDelete();
            $table->foreignIdFor(Agent::class)->constrained()->cascadeOnDelete();
            $table->foreignId('chat_conversation_id')->nullable()->constrained('chat_conversations')->nullOnDelete();

            $table->foreignId('chat_message_id')->nullable()->constrained('chat_messages')->nullOnDelete();

            $table->string('status')->default('pending');

            $table->text('values')->nullable();

            $table->string('values_checksum', 64)->nullable();

            $table->string('request_id')->nullable();
            $table->ipAddress()->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            $table->index(['agent_id', 'created_at']);

            $table->index(['form_definition_id', 'values_checksum']);
        });

        Schema::dropIfExists('leads');
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('form_definitions');
    }
};
