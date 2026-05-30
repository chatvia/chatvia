<?php

use App\Auth\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('name');

            // Optional reference to a reusable library prompt (Settings →
            // Prompts). Persona-level, shared across every surface this
            // template configures.
            $table->foreignId('agent_prompt_id')->nullable()->constrained('agent_prompts')->nullOnDelete();

            // Whether agents using this template load without a signed token.
            // Read-through on Agent::isPublic when attached.
            $table->boolean('is_public')->default(false);

            // Per-surface configuration. Each JSON blob holds everything for
            // that surface — text, starters, models (array for hosted_chat,
            // single for widget/bar), llm_settings, color, avatar/logo paths,
            // and surface-specific UI (position/size for widget, trigger for
            // bar, embed flags for hosted_chat). A template can populate any
            // subset; surfaces left null mean the agent's own settings stand.
            $table->json('hosted_chat_config')->nullable();
            $table->json('widget_config')->nullable();
            $table->json('bar_config')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_templates');
    }
};
