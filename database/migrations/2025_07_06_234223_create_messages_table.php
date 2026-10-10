<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Enums\ToneEnum;
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
        Schema::create('chat_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignIdFor(Agent::class)->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->nullable()->constrained()->cascadeOnDelete();
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
            $table->timestamps();

            $table->index(['agent_id', 'created_at']);
            $table->index(['agent_id', 'last_activity_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['agent_id', 'country_id']);
        });

        Schema::create('chat_conversations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignId('chat_session_id')->constrained('chat_sessions')->cascadeOnDelete();

            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Language::class)->nullable()->index()->constrained()->nullOnDelete();

            $table->enum('source', ['hosted_chat', 'widget', 'bar', 'telegram', 'slack', 'messenger', 'whatsapp', 'msteams', 'instagram']);
            $table->boolean('is_visible')->default(false);
            $table->text('title')->nullable();

            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->nullable();
            $table->enum('tone', ToneEnum::values())->nullable();

            $table->timestamp('analyzed_at')->nullable();
            // Model calls spent analysing this conversation; the hourly pass stops
            // retrying at chatvia.analytics.analysis.max_attempts.
            $table->unsignedSmallInteger('analysis_attempts')->default(0);

            $table->foreignIdFor(User::class, 'handoff_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->enum('handoff_status', ['ai', 'pending', 'human', 'returned'])->default('ai');

            $table->timestamp('handoff_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();

            $table->timestamp('expires_at')->nullable();

            $table->string('deleted_by', 10)->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['chat_session_id', 'created_at']);
            $table->index(['chat_session_id', 'updated_at']);

            $table->index(['agent_id', 'created_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignId('chat_conversation_id')->constrained('chat_conversations')->cascadeOnDelete();

            $table->foreignIdFor(Llm::class)->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignIdFor(AgentQuestionAnswer::class)->nullable()->index()->constrained()->nullOnDelete();

            $table->string('generation_id')->nullable();

            $table->enum('source', ['hosted_chat', 'widget', 'bar', 'telegram', 'slack', 'messenger', 'whatsapp', 'msteams', 'instagram', 'playground', 'handoff'])->nullable();

            $table->text('content')->nullable();

            $table->bm25vector('content_bm25')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->enum('role', ['user', 'assistant', 'agent']);
            $table->decimal('confidence_score', 3, 2)->nullable();

            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->nullable();
            $table->enum('tone', ToneEnum::values())->nullable();

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

    private function createPartialIndexes(): void
    {
        DB::statement("CREATE INDEX messages_assistant_active_idx ON chat_messages (chat_conversation_id, created_at) WHERE role = 'assistant'");
        DB::statement("CREATE INDEX messages_user_active_idx ON chat_messages (chat_conversation_id, created_at) WHERE role = 'user'");
        DB::statement('CREATE INDEX messages_feedback_date_idx ON chat_messages (feedback, created_at) WHERE feedback IS NOT NULL');

        DB::statement('CREATE INDEX chat_messages_content_bm25 ON chat_messages USING bm25 (content_bm25 bm25_catalog.bm25_ops)');

        DB::statement("CREATE INDEX conversations_handoff_pending_idx ON chat_conversations (created_at) WHERE handoff_status = 'pending'");
        DB::statement("CREATE INDEX conversations_handoff_human_idx ON chat_conversations (last_activity_at) WHERE handoff_status = 'human'");

        DB::statement('CREATE INDEX conversations_visible_active_idx ON chat_conversations (chat_session_id, updated_at) WHERE is_visible = true AND deleted_at IS NULL');

        DB::statement('CREATE INDEX conversations_agent_visible_idx ON chat_conversations (agent_id, updated_at) WHERE is_visible = true AND deleted_at IS NULL');

        DB::statement('CREATE INDEX conversations_expires_cleanup_idx ON chat_conversations (expires_at) WHERE expires_at IS NOT NULL AND deleted_at IS NULL');

        DB::statement('CREATE INDEX conversations_pending_analysis_idx ON chat_conversations (created_at) WHERE analyzed_at IS NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        $indexes = [
            'messages_assistant_active_idx',
            'messages_user_active_idx',
            'messages_feedback_date_idx',
            'chat_messages_content_bm25',
            'conversations_handoff_pending_idx',
            'conversations_handoff_human_idx',
            'conversations_visible_active_idx',
            'conversations_agent_visible_idx',
            'conversations_expires_cleanup_idx',
            'conversations_pending_analysis_idx',
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
