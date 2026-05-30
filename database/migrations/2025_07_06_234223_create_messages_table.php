<?php

use App\Auth\Models\User;
use App\Country\Models\Country;
use App\Language\Models\Language;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\Llm;
use Domain\Analytics\Enums\ToneEnum;
use Domain\Chat\Models\ChatTopic;
use Domain\Sources\QA\Models\AgentQuestionAnswer;
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
            $table->string('time_zone', 64)->nullable(); // IANA tz names can be 32+ chars
            $table->boolean('is_proxy')->nullable();

            $table->text('user_agent')->nullable();

            $table->string('device_type')->nullable();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();

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
            $table->uuid('external_id')->unique();

            $table->foreignId('chat_session_id')->constrained('chat_sessions')->cascadeOnDelete();
            $table->foreignIdFor(Language::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(ChatTopic::class)->nullable()->constrained()->nullOnDelete();

            // Which surface this conversation originated from — Hosted Chat,
            // Widget, or Bar. Set once at insert; drives chat-log filtering and
            // analytics segmentation. The agent itself no longer has a `type`;
            // a single agent can be exposed through any/all three surfaces.
            $table->enum('source', ['hosted_chat', 'widget', 'bar'])->index();

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
            $table->index('chat_topic_id');
            $table->index('updated_at'); // ORDER BY latest activity in chat-log
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignId('chat_conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignIdFor(Llm::class)->nullable()->constrained()->restrictOnDelete();
            $table->foreignIdFor(AgentQuestionAnswer::class)->nullable()->constrained()->nullOnDelete();

            $table->string('generation_id')->nullable();

            $table->unsignedInteger('message_credits')->default(1);

            $table->string('source')->nullable();

            $table->text('content')->nullable();
            $table->json('metadata')->nullable();

            $table->enum('role', ['user', 'assistant', 'agent']);
            $table->decimal('confidence_score', 5, 4)->nullable();

            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->nullable();
            $table->enum('tone', ToneEnum::values())->nullable();

            $table->enum('feedback', ['positive', 'negative'])->nullable();

            $table->boolean('is_response_from_qa')->nullable();
            $table->boolean('llm_found_answer')->nullable();

            $table->unsignedInteger('response_time_ms')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->decimal('cost', 10, 6)->nullable();

            $table->boolean('is_usage_counted')->nullable();

            $table->timestamps();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('read_at')->nullable();

            // Hot path: paginating a conversation's messages newest-first.
            $table->index(['chat_conversation_id', 'created_at']);
            // Per-model analytics ordering.
            $table->index(['llm_id', 'created_at']);
        });

        $this->createPartialIndexes();
    }

    /**
     * Partial indexes covering the queries that actually run. Each filter is
     * either a hot dashboard predicate (assistant role, feedback present) or
     * an admin sweep (expires_at cleanup). Everything dropped from the
     * original migration was either dead, dominated by another index, or
     * indexing a near-boolean column with no selectivity.
     */
    private function createPartialIndexes(): void
    {
        // Latest-message lookups per conversation, restricted to assistant.
        DB::statement("CREATE INDEX messages_assistant_active_idx ON chat_messages (chat_conversation_id, created_at) WHERE role = 'assistant'");
        // Same for user side — drives unread / read-receipt queries.
        DB::statement("CREATE INDEX messages_user_active_idx ON chat_messages (chat_conversation_id, created_at) WHERE role = 'user'");
        // Feedback dashboards: positive/negative ratios over time.
        DB::statement('CREATE INDEX messages_feedback_date_idx ON chat_messages (feedback, created_at) WHERE feedback IS NOT NULL');
        // TTL-style cleanup sweep.
        DB::statement('CREATE INDEX messages_expires_cleanup_idx ON chat_messages (expires_at) WHERE expires_at IS NOT NULL');
    }

    public function down(): void
    {
        $indexes = [
            'messages_assistant_active_idx',
            'messages_user_active_idx',
            'messages_feedback_date_idx',
            'messages_expires_cleanup_idx',
        ];

        foreach ($indexes as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }

        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
        Schema::dropIfExists('chat_sessions');
    }
};
