<?php

use App\Workspace\Models\User;
use App\Country\Models\Country;
use App\Language\Models\Language;
use Domain\Agent\Models\Agent;
use Domain\Llm\Models\Llm;
use Domain\Analytics\Enums\ToneEnum;
use Domain\Source\QA\Models\AgentQuestionAnswer;
use Domain\Topic\Models\ChatTopic;
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
            $table->index('country_id');
        });

        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignId('chat_session_id')->constrained('chat_sessions')->cascadeOnDelete();
            $table->foreignIdFor(Language::class)->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignIdFor(ChatTopic::class)->nullable()->constrained()->nullOnDelete();

            $table->enum('source', ['hosted_chat', 'widget', 'bar']);
            $table->boolean('is_visible')->default(false);
            $table->string('title')->nullable();

            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->nullable();
            $table->enum('tone', ToneEnum::values())->nullable();

            $table->foreignIdFor(User::class, 'handoff_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->enum('handoff_status', ['ai', 'pending', 'human', 'returned'])->default('ai');

            $table->timestamp('handoff_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();

            $table->string('deleted_by', 10)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['chat_session_id', 'created_at']);
            $table->index(['chat_session_id', 'updated_at']);
            $table->index('chat_topic_id');
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignId('chat_conversation_id')->constrained('chat_conversations')->cascadeOnDelete();

            $table->foreignIdFor(Llm::class)->nullable()->index()->constrained()->nullOnDelete();
            $table->foreignIdFor(AgentQuestionAnswer::class)->nullable()->index()->constrained()->nullOnDelete();

            $table->string('generation_id')->nullable();

            $table->unsignedTinyInteger('message_credits')->default(1);

            $table->string('source')->nullable();

            $table->text('content')->nullable();
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

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->index(['chat_conversation_id', 'created_at']);
        });

        $this->createPartialIndexes();
    }

    private function createPartialIndexes(): void
    {
        DB::statement("CREATE INDEX messages_assistant_active_idx ON chat_messages (chat_conversation_id, created_at) WHERE role = 'assistant'");
        DB::statement("CREATE INDEX messages_user_active_idx ON chat_messages (chat_conversation_id, created_at) WHERE role = 'user'");
        DB::statement('CREATE INDEX messages_feedback_date_idx ON chat_messages (feedback, created_at) WHERE feedback IS NOT NULL');
        DB::statement('CREATE INDEX messages_expires_cleanup_idx ON chat_messages (expires_at) WHERE expires_at IS NOT NULL');

        DB::statement("CREATE INDEX conversations_handoff_pending_idx ON chat_conversations (created_at) WHERE handoff_status = 'pending'");
        DB::statement("CREATE INDEX conversations_handoff_human_idx ON chat_conversations (last_activity_at) WHERE handoff_status = 'human'");

        DB::statement('CREATE INDEX conversations_visible_active_idx ON chat_conversations (chat_session_id, updated_at) WHERE is_visible = true AND deleted_at IS NULL');
    }

    public function down(): void
    {
        $indexes = [
            'messages_assistant_active_idx',
            'messages_user_active_idx',
            'messages_feedback_date_idx',
            'messages_expires_cleanup_idx',
            'conversations_handoff_pending_idx',
            'conversations_handoff_human_idx',
            'conversations_visible_active_idx',
        ];

        foreach ($indexes as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }

        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
        Schema::dropIfExists('chat_sessions');
    }
};
