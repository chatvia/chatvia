<?php

use App\Enums\ToneEnum;
use App\Models\Agent;
use App\Models\AgentQuestionAnswer;
use App\Models\ChatTopic;
use App\Models\Country;
use App\Models\Language;
use App\Models\Llm;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique()->index();

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
            $table->string('time_zone', 10)->nullable();
            $table->boolean('is_proxy')->nullable();

            $table->text('user_agent')->nullable();

            $table->string('device_type')->nullable()->index();
            $table->string('browser')->nullable()->index();
            $table->string('os')->nullable()->index();

            $table->text('referrer_url')->nullable();
            $table->text('page_url')->nullable();
            $table->string('locale', 50)->nullable();

            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamps();

            $table->index(['agent_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['country_id', 'city']);
            $table->index('created_at');
        });

        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique()->index();

            $table->foreignId('chat_session_id')->constrained('chat_sessions')->cascadeOnDelete();
            $table->foreignIdFor(Language::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(ChatTopic::class)->nullable()->constrained()->nullOnDelete();

            $table->boolean('visible')->index()->default(false);

            $table->string('title')->nullable();

            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->nullable();
            $table->enum('tone', ToneEnum::values())->nullable();

            $table->foreignIdFor(User::class, 'handoff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('handoff_status', ['ai', 'pending', 'human', 'returned'])->default('ai')->index();

            $table->timestamp('handoff_at')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();

            $table->enum('deleted_by', ['user', 'admin'])->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['chat_session_id', 'created_at']);
            $table->index('chat_topic_id'); // For topic filtering
            $table->index('updated_at'); // For ORDER BY latest activity
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique()->index();

            $table->foreignId('chat_conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignIdFor(Llm::class)->nullable()->constrained()->restrictOnDelete();
            $table->foreignIdFor(AgentQuestionAnswer::class)->nullable()->constrained()->nullOnDelete();

            $table->string('generation_id')->nullable()->index();

            $table->unsignedInteger('message_credits')->default(1);

            $table->string('source')->nullable()->index();

            $table->text('content')->nullable();
            $table->json('metadata')->nullable();

            $table->enum('role', ['user', 'assistant', 'agent'])->index();
            $table->decimal('confidence_score', 5, 4)->nullable();

            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->nullable();
            $table->enum('tone', ToneEnum::values())->nullable();

            $table->enum('feedback', ['positive', 'negative'])->nullable();

            $table->boolean('is_response_from_qa')->nullable()->index();

            $table->boolean('llm_found_answer')->nullable()->index();

            $table->unsignedInteger('response_time_ms')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->decimal('cost', 10, 6)->nullable()->index();

            $table->boolean('is_usage_counted')->nullable()->index();

            $table->timestamps();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->index(['chat_conversation_id', 'created_at']);
            $table->index(['role', 'created_at']);
            $table->index(['llm_id', 'created_at']);
            $table->index(['feedback', 'created_at']);

            // Indexes for conversation filtering (sentiment, tone, confidence, source)
            $table->index(['chat_conversation_id', 'sentiment']);
            $table->index(['chat_conversation_id', 'tone']);
            $table->index(['chat_conversation_id', 'confidence_score']);
            $table->index(['chat_conversation_id', 'source']);
        });

        // Create basic indexes immediately (within transaction)
        $this->createBasicIndexes();
    }

    private function createBasicIndexes(): void
    {
        // Create regular indexes (non-concurrent) within the transaction
        DB::statement('CREATE INDEX messages_active_idx ON chat_messages (chat_conversation_id, created_at)');
        DB::statement("CREATE INDEX messages_assistant_active_idx ON chat_messages (chat_conversation_id, created_at) WHERE role = 'assistant'");
        DB::statement("CREATE INDEX messages_user_active_idx ON chat_messages (chat_conversation_id, created_at) WHERE role = 'user'");

        // Date-based indexes for analytics
        DB::statement('CREATE INDEX messages_created_date_idx ON chat_messages USING btree (date(created_at))');
        DB::statement('CREATE INDEX messages_year_month_idx ON chat_messages USING btree (extract(year from created_at), extract(month from created_at))');

        // Performance indexes for common queries
        DB::statement('CREATE INDEX messages_feedback_date_idx ON chat_messages (feedback, created_at) WHERE feedback IS NOT NULL');
        DB::statement('CREATE INDEX messages_expires_cleanup_idx ON chat_messages (expires_at) WHERE expires_at IS NOT NULL');

        // Conversation-level indexes
        DB::statement('CREATE INDEX conversations_active_idx ON chat_conversations (chat_session_id, created_at)');

        // Multi-language full-text search indexes
        $this->createMultiLanguageSearchIndexes();
    }

    private function createMultiLanguageSearchIndexes(): void
    {
        // Simple text search using trigrams (language-agnostic)
        DB::statement('CREATE INDEX messages_content_trigram_idx ON chat_messages USING gin(content gin_trgm_ops)');

        // Multi-language full-text search with simple configuration
        DB::statement('CREATE INDEX messages_content_simple_search_idx ON chat_messages USING gin(to_tsvector(\'simple\', content))');

        // Conversation title search (language-agnostic)
        DB::statement('CREATE INDEX conversations_title_trigram_idx ON chat_conversations USING gin(title gin_trgm_ops) WHERE title IS NOT NULL');
    }

    public function down(): void
    {
        // Drop custom indexes first
        $this->dropCustomIndexes();

        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
        Schema::dropIfExists('chat_sessions');
    }

    private function dropCustomIndexes(): void
    {
        $indexes = [
            'messages_active_idx',
            'messages_assistant_active_idx',
            'messages_user_active_idx',
            'messages_created_date_idx',
            'messages_year_month_idx',
            'messages_feedback_date_idx',
            'messages_expires_cleanup_idx',
            'conversations_active_idx',
            'sessions_type_agent_date_idx',
            'sessions_user_recent_idx',
            // Multi-language search indexes
            'messages_content_trigram_idx',
            'messages_content_simple_search_idx',
            'conversations_title_trigram_idx',
            // Language-specific indexes
            'messages_content_en_idx',
            'messages_content_es_idx',
            'messages_content_fr_idx',
            'messages_content_de_idx',
            'messages_content_pt_idx',
            'messages_content_ru_idx',
            'messages_content_ar_idx',
        ];

        foreach ($indexes as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }
    }
};
