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
        // Embedding columns use the install's vector type and size (EmbeddingStorage).

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

            $table->embedding('embedding')->nullable();
            $table->bm25vector('embedding_bm25')->nullable();

            $table->timestamps();

            $table->unique(['agent_id', 'slug']);
        });

        DB::statement('CREATE INDEX form_definitions_embedding_bm25 ON form_definitions USING bm25 (embedding_bm25 bm25_catalog.bm25_ops)');
        // No vector index: an agent reaches a few dozen forms at most, so its
        // search ranks them by exact distance (an approximate index over every
        // workspace's rows would only add work and miss some).

        Schema::create('form_submissions', function (Blueprint $table): void {
            $table->id();

            $table->uuid('external_id')->unique();

            $table->foreignId('form_definition_id')->constrained('form_definitions')->cascadeOnDelete();
            // The workspace rides on the agent, conversation and message keys,
            // so a lead can never point at another workspace's agent,
            // conversation or message.
            $table->foreignId('workspace_id');
            $table->foreignIdFor(Agent::class);
            $table->foreignId('chat_conversation_id')->nullable()->index();

            $table->foreignId('chat_message_id')->nullable()->index();

            $table->string('status')->default('pending');

            $table->text('values')->nullable();

            $table->string('values_checksum', 64)->nullable();

            $table->string('request_id')->nullable();
            $table->ipAddress()->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            $table->foreign(['workspace_id', 'agent_id'])->references(['workspace_id', 'id'])->on('agents')->cascadeOnDelete();

            $table->index(['agent_id', 'created_at']);

            $table->index(['form_definition_id', 'values_checksum']);
        });

        // Deleting the conversation keeps the lead and clears only its
        // conversation link (Postgres can null one column of a composite key).
        DB::statement('ALTER TABLE form_submissions ADD CONSTRAINT form_submissions_workspace_conversation_foreign FOREIGN KEY (workspace_id, chat_conversation_id) REFERENCES chat_conversations (workspace_id, id) ON DELETE SET NULL (chat_conversation_id)');
        DB::statement('ALTER TABLE form_submissions ADD CONSTRAINT form_submissions_workspace_message_foreign FOREIGN KEY (workspace_id, chat_message_id) REFERENCES chat_messages (workspace_id, id) ON DELETE SET NULL (chat_message_id)');

        // The abandoned-form prune (every few minutes): pending rows past expiry.
        DB::statement("CREATE INDEX form_submissions_pending_expiry_idx ON form_submissions (expires_at) WHERE status = 'pending'");
        // A form's leads list and export, newest first.
        DB::statement("CREATE INDEX form_submissions_submitted_idx ON form_submissions (form_definition_id, id) WHERE status = 'submitted'");
        // The workspace lead-retention prune.
        DB::statement("CREATE INDEX form_submissions_workspace_submitted_idx ON form_submissions (workspace_id, submitted_at) WHERE status = 'submitted'");

        Schema::dropIfExists('leads');
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('form_definitions');
    }
};
