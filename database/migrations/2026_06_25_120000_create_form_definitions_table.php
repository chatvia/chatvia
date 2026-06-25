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
        // A reusable form an agent can render inline in the chat. Agent-scoped
        // (like leads), so it dies with the agent and is employee-deletion safe
        // (agents belong to the workspace owner, not the acting teammate).
        Schema::create('form_definitions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignIdFor(Agent::class)->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug', 64);
            $table->enum('form_type', ['lead', 'booking', 'qualification', 'generic'])->default('lead');

            // Normalized field array (our contract) — the single source of truth
            // the server derives both the FormKit schema and the validation rules
            // from. Never raw FormKit `$el`/`$cmp` nodes.
            $table->jsonb('schema');
            // field key → lead attribute, for lead/qualification forms.
            $table->jsonb('lead_field_map')->nullable();

            $table->string('success_message')->nullable();
            // What the form is for / when the agent should show it — feeds the
            // LLM so it renders the right form by intent, not title match.
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            // Semantic-router columns so an agent's forms are picked by INTENT
            // (the visitor's message), not by title match. Mirrors `functions`:
            // full fp32 vector (not halfvec) because FormHybridSearch hard-codes
            // `<-> ?::vector(1024)` against a vector_l2_ops vchordrq index — a
            // halfvec column would drop the index on an ops-class mismatch. The
            // form catalog per agent is tiny, so the halfvec storage saving is
            // negligible. `embedding_bm25` is the llmlingua2 sparse vector.
            $table->vector('embedding', 1024)->nullable();
            $table->bm25vector('embedding_bm25')->nullable();

            $table->timestamps();

            $table->unique(['agent_id', 'slug']);
        });

        DB::statement('CREATE INDEX form_definitions_embedding_bm25 ON form_definitions USING bm25 (embedding_bm25 bm25_catalog.bm25_ops)');
        DB::statement('CREATE INDEX form_definitions_embedding_vchordrq ON form_definitions USING vchordrq (embedding vector_l2_ops)');

        Schema::create('form_submissions', function (Blueprint $table): void {
            $table->id();
            // external_id IS the per-render form_instance_id — the idempotency key
            // the submit endpoint claims exactly once (lockForUpdate).
            $table->uuid('external_id')->unique();

            $table->foreignId('form_definition_id')->constrained('form_definitions')->cascadeOnDelete();
            $table->foreignIdFor(Agent::class)->constrained()->cascadeOnDelete();
            $table->foreignId('chat_conversation_id')->nullable()->constrained('chat_conversations')->nullOnDelete();
            // The assistant message that carries the form artifact — set on submit
            // so the submitted state can be written back into its metadata. SET NULL
            // so a submission survives the message being deleted / retention-pruned.
            $table->foreignId('chat_message_id')->nullable()->constrained('chat_messages')->nullOnDelete();

            // Plain string, not enum(): the valid values live as STATUS_* constants
            // on the FormSubmission model (the app is the source of truth). A DB
            // enum() bakes a CHECK constraint, so adding a status later would need a
            // constraint-altering migration or inserts throw 23514 — the same trap
            // as chat_conversations.source.
            $table->string('status')->default('pending');

            // The submission's answers. Encrypted at rest (model cast
            // `encrypted:array`) since they hold PII (name, email, phone, …) —
            // form_submissions is the single store for all captured contacts.
            $table->text('values')->nullable();

            // Plaintext fingerprint of `values` for de-duplication: the encrypted
            // column uses a random IV so identical answers can't be matched on it.
            // A submit that repeats an already-stored answer set for the same form
            // is dropped instead of stored twice (the `leads` table's old
            // (agent_id, email_hash) unique guard, generalised to any form).
            $table->string('values_checksum', 64)->nullable();

            $table->string('request_id')->nullable();
            $table->ipAddress()->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            $table->index(['agent_id', 'created_at']);
            // Fast dedup lookup: "is this answer set already submitted to this form?"
            $table->index(['form_definition_id', 'values_checksum']);
        });

        // Lead capture is unified on form_submissions — the standalone `leads`
        // table is retired (its create migration was removed; this drops it for
        // already-migrated environments). Pre-release, so no data to preserve.
        Schema::dropIfExists('leads');
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('form_definitions');
    }
};
