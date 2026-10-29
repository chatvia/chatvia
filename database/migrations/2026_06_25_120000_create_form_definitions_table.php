<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Retrieval\Actions\ConfigureLexicalIndexesAction;
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
            $table->string('subtitle', 500)->nullable();
            $table->string('slug', 64);
            $table->enum('form_type', ['lead', 'booking', 'qualification', 'generic'])->default('lead');

            $table->jsonb('schema');

            $table->jsonb('lead_field_map')->nullable();

            $table->string('success_message')->nullable();

            $table->text('agent_instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('managed_by', 64)->nullable();

            $table->embedding('embedding')->nullable();
            $table->text('search_text')->nullable();

            $table->timestamps();

            $table->unique(['agent_id', 'slug']);
        });

        app(ConfigureLexicalIndexesAction::class)->execute('form_definitions');

        Schema::create('form_submissions', function (Blueprint $table): void {
            $table->id();

            $table->uuid('external_id')->unique();

            $table->foreignId('form_definition_id')->constrained('form_definitions')->cascadeOnDelete();
            $table->foreignId('workspace_id');
            $table->foreignIdFor(Agent::class);
            $table->foreignId('chat_conversation_id')->nullable()->index();

            $table->foreignId('chat_message_id')->nullable()->index();

            $table->enum('status', ['pending', 'submitted', 'expired'])->default('pending');

            $table->jsonb('values')->nullable();

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

        DB::statement('ALTER TABLE form_submissions ADD CONSTRAINT form_submissions_workspace_conversation_foreign FOREIGN KEY (workspace_id, chat_conversation_id) REFERENCES chat_conversations (workspace_id, id) ON DELETE SET NULL (chat_conversation_id)');
        DB::statement('ALTER TABLE form_submissions ADD CONSTRAINT form_submissions_workspace_message_foreign FOREIGN KEY (workspace_id, chat_message_id) REFERENCES chat_messages (workspace_id, id) ON DELETE SET NULL (chat_message_id)');

        DB::statement("CREATE INDEX form_submissions_pending_expiry_idx ON form_submissions (expires_at) WHERE status = 'pending'");
        DB::statement("CREATE INDEX form_submissions_submitted_idx ON form_submissions (form_definition_id, id) WHERE status = 'submitted'");
        DB::statement("CREATE INDEX form_submissions_workspace_submitted_idx ON form_submissions (workspace_id, submitted_at) WHERE status = 'submitted'");
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('form_definitions');
    }
};
