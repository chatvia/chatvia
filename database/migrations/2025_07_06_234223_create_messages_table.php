<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Enums\Tone;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Domain\Llm\Models\Llm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Support\Localization\Country\Models\Country;
use Support\Localization\Language\Models\Language;

return new class extends Migration
{
    public function up(): void
    {
        // The chat tables carry their workspace on every row, and each key to a
        // parent pairs it with the workspace: (workspace_id, agent_id) →
        // agents, (workspace_id, chat_session_id) → chat_sessions and so on. A
        // row that points at another workspace's agent, chatter, session or
        // conversation is refused by the database, whatever the code does.
        Schema::create('chat_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Agent::class)->nullable();
            // Who is chatting: a staff account (user_id) or a chatter
            // (chatter_id), never both; neither for an anonymous visitor.
            $table->foreignIdFor(User::class)->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('chatter_id')->nullable();
            $table->foreignIdFor(Country::class)->nullable()->constrained()->nullOnDelete();

            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->ipAddress()->nullable();

            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->string('zip_code', 20)->nullable();
            $table->string('time_zone', 64)->nullable();
            $table->boolean('is_proxy')->nullable();

            $table->text('user_agent')->nullable();

            $table->string('device_type')->nullable();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();

            $table->text('referrer_url')->nullable();
            $table->text('page_url')->nullable();
            $table->string('locale', 10)->nullable();

            $table->timestamp('last_activity_at')->nullable();
            // When the visitor's details above were cleared under the
            // workspace's "anonymize after" setting; null until then.
            $table->timestamp('anonymized_at')->nullable();
            $table->timestamps();

            $table->foreign(['workspace_id', 'agent_id'])->references(['workspace_id', 'id'])->on('agents')->cascadeOnDelete();
            $table->foreign(['workspace_id', 'chatter_id'])->references(['workspace_id', 'id'])->on('chatters')->cascadeOnDelete();
            $table->unique(['workspace_id', 'id']);

            $table->index(['agent_id', 'created_at']);
            $table->index(['agent_id', 'country_id']);
        });

        DB::statement('ALTER TABLE chat_sessions ADD CONSTRAINT chat_sessions_one_viewer CHECK (num_nonnulls(user_id, chatter_id) <= 1)');

        Schema::create('chat_conversations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignId('workspace_id');
            $table->foreignId('chat_session_id');
            $table->foreignId('agent_id');
            $table->foreignIdFor(Language::class)->nullable()->index()->constrained()->nullOnDelete();

            $table->enum('source', ['hosted_chat', 'widget', 'bar', 'telegram', 'slack', 'messenger', 'whatsapp', 'msteams', 'instagram', 'api']);
            $table->boolean('is_visible')->default(false);
            $table->text('title')->nullable();

            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->nullable();
            $table->enum('tone', Tone::values())->nullable();

            $table->timestamp('analyzed_at')->nullable();
            // Model calls spent analysing this conversation; the hourly pass stops
            // retrying at chatvia.analytics.analysis.max_attempts.
            $table->unsignedSmallInteger('analysis_attempts')->default(0);

            $table->foreignIdFor(User::class, 'handoff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('handoff_status', ['ai', 'pending', 'human', 'returned'])->default('ai');

            $table->timestamp('handoff_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();

            $table->timestamp('expires_at')->nullable();

            $table->string('deleted_by', 10)->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign(['workspace_id', 'chat_session_id'])->references(['workspace_id', 'id'])->on('chat_sessions')->cascadeOnDelete();
            $table->foreign(['workspace_id', 'agent_id'])->references(['workspace_id', 'id'])->on('agents')->cascadeOnDelete();
            $table->unique(['workspace_id', 'id']);

            $table->index(['chat_session_id', 'created_at']);

            $table->index(['agent_id', 'created_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignId('workspace_id');
            $table->foreignId('chat_conversation_id');

            $table->foreignIdFor(Llm::class)->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignIdFor(AgentQuestionAnswer::class)->nullable()->constrained()->nullOnDelete();

            $table->string('generation_id')->nullable();
            // The Spectra trace of the turn that produced an assistant reply
            // (W3C-compatible; display-only, so no index).
            $table->uuid('trace_id')->nullable();

            $table->enum('source', ['hosted_chat', 'widget', 'bar', 'telegram', 'slack', 'messenger', 'whatsapp', 'msteams', 'instagram', 'api', 'playground', 'handoff'])->nullable();

            $table->text('content')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->enum('role', ['user', 'assistant', 'agent']);
            $table->decimal('confidence_score', 3, 2)->nullable();

            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->nullable();
            $table->enum('tone', Tone::values())->nullable();

            $table->enum('feedback', ['positive', 'negative'])->nullable();

            $table->boolean('is_response_from_qa')->nullable();
            $table->boolean('has_llm_found_answer')->nullable();

            $table->unsignedMediumInteger('response_time_ms')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedBigInteger('cost_in_micro_cents')->nullable();

            $table->boolean('is_usage_counted')->nullable();

            $table->timestamps();

            $table->timestamp('read_at')->nullable();

            $table->foreign(['workspace_id', 'chat_conversation_id'])->references(['workspace_id', 'id'])->on('chat_conversations')->cascadeOnDelete();
            $table->unique(['workspace_id', 'id']);

            $table->index(['chat_conversation_id', 'created_at']);
        });

        Schema::create('chat_attachments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignId('chat_conversation_id')->nullable()->constrained('chat_conversations')->cascadeOnDelete();

            $table->foreignId('chat_message_id')->nullable()->constrained('chat_messages')->cascadeOnDelete();

            $table->string('filepath', 2048);
            $table->string('filename');
            $table->string('mime_type', 100)->default('application/octet-stream');
            $table->unsignedBigInteger('size_bytes')->default(0);

            $table->unsignedInteger('pages')->nullable();

            $table->timestamps();

            $table->index(['chat_conversation_id', 'mime_type']);
            $table->index('chat_message_id');
        });

        $this->createPartialIndexes();
    }

    /**
     * Partial and special indexes. Columns that are mostly NULL get a partial
     * index (`WHERE … IS NOT NULL`): the foreign-key checks and lookups by
     * value still use it, and rows without a value cost no index write.
     */
    private function createPartialIndexes(): void
    {
        DB::statement('CREATE INDEX chat_sessions_user_idx ON chat_sessions (user_id, created_at) WHERE user_id IS NOT NULL');
        DB::statement('CREATE INDEX chat_sessions_chatter_idx ON chat_sessions (chatter_id, created_at) WHERE chatter_id IS NOT NULL');
        // The daily anonymize pass: a workspace's sessions not yet cleared.
        DB::statement('CREATE INDEX chat_sessions_to_anonymize_idx ON chat_sessions (workspace_id, created_at) WHERE anonymized_at IS NULL');

        DB::statement('CREATE INDEX messages_qa_idx ON chat_messages (agent_question_answer_id) WHERE agent_question_answer_id IS NOT NULL');
        // The chat log's feedback filter: does a conversation have a rated message.
        DB::statement('CREATE INDEX messages_feedback_idx ON chat_messages (chat_conversation_id, feedback) WHERE feedback IS NOT NULL');

        DB::statement('CREATE INDEX conversations_handoff_user_idx ON chat_conversations (handoff_user_id) WHERE handoff_user_id IS NOT NULL');
        // An agent's waiting handoffs, newest activity first.
        DB::statement("CREATE INDEX conversations_handoff_pending_idx ON chat_conversations (agent_id, updated_at) WHERE handoff_status = 'pending' AND deleted_at IS NULL");

        // The chat log: an agent's visible conversations, keyset-paged by
        // (updated_at, id).
        DB::statement('CREATE INDEX conversations_agent_visible_idx ON chat_conversations (agent_id, updated_at, id) WHERE is_visible = true AND deleted_at IS NULL');

        DB::statement('CREATE INDEX conversations_expires_cleanup_idx ON chat_conversations (expires_at) WHERE expires_at IS NOT NULL AND deleted_at IS NULL');
        // The trash purge, which finally removes soft-deleted conversations.
        DB::statement('CREATE INDEX conversations_trashed_idx ON chat_conversations (deleted_at) WHERE deleted_at IS NOT NULL');

        // Hidden (never-used) conversations are not analysed.
        DB::statement('CREATE INDEX conversations_pending_analysis_idx ON chat_conversations (created_at) WHERE analyzed_at IS NULL AND deleted_at IS NULL AND is_visible = true');

        // Install-wide time ranges (the admin panel's platform analytics).
        DB::statement('CREATE INDEX chat_conversations_created_brin ON chat_conversations USING brin (created_at)');

        // The orphan-attachment prune: uploads never tied to a conversation.
        DB::statement('CREATE INDEX chat_attachments_orphan_idx ON chat_attachments (created_at) WHERE chat_conversation_id IS NULL');
    }

    public function down(): void
    {
        $indexes = [
            'chat_sessions_user_idx',
            'chat_sessions_chatter_idx',
            'chat_sessions_to_anonymize_idx',
            'messages_qa_idx',
            'messages_feedback_idx',
            'conversations_handoff_user_idx',
            'conversations_handoff_pending_idx',
            'conversations_agent_visible_idx',
            'conversations_expires_cleanup_idx',
            'conversations_trashed_idx',
            'conversations_pending_analysis_idx',
            'chat_conversations_created_brin',
            'chat_attachments_orphan_idx',
        ];

        foreach ($indexes as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }

        Schema::dropIfExists('chat_attachments');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
        Schema::dropIfExists('chat_sessions');
    }
};
