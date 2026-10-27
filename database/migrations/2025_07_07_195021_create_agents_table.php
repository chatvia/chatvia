<?php

use Domain\Membership\Team\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table): void {
            $table->id();
            $table->string('external_id', 20)->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Team::class)->index()->constrained()->noActionOnDelete();
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->text('system_instructions')->nullable();
            $table->foreignId('agent_prompt_id')->nullable()->index()->constrained('agent_prompts')->nullOnDelete();

            $table->foreignId('llm_id')->nullable()->index()->constrained('llms')->nullOnDelete();
            $table->boolean('is_public')->default(false);
            $table->jsonb('metadata')->nullable();
            $table->enum('source', ['dashboard', 'api'])->default('dashboard');

            $table->text('auth_secret')->nullable();

            $table->timestamps();
            // Set the moment an agent is deleted: it disappears at once while a
            // queued job removes its chats and sources in small batches.
            $table->softDeletes();

            // Lets chat rows point at (workspace_id, agent_id) together, so the
            // database refuses a row whose agent is in another workspace. Also
            // serves lookups by workspace.
            $table->unique(['workspace_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
