<?php

use App\Auth\Models\User;
use App\Teams\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 20)->unique();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Team::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sub_domain')->nullable()->unique();
            $table->string('domain')->nullable()->unique();
            $table->text('system_instructions')->nullable();
            // Optional reference to a reusable library prompt (Settings → Prompts).
            // When set it takes precedence over system_instructions; nullOnDelete so
            // deleting a library prompt detaches it and the agent falls back.
            $table->foreignId('agent_prompt_id')->nullable()->constrained('agent_prompts')->nullOnDelete();
            // Optional reference to a reusable account-level template (Settings →
            // Templates). When set, the agent's appearance/widget/models/starters/
            // prompt are read from the template at runtime; nullOnDelete detaches.
            $table->foreignId('agent_template_id')->nullable()->constrained('agent_templates')->nullOnDelete();
            $table->boolean('is_public')->default(false);
            $table->boolean('analytics_enabled')->default(true);
            $table->json('metadata')->nullable();
            $table->enum('created_via', ['internal', 'external'])->default('internal')->index();

            // Encrypted at rest. HMAC-signs this agent's chat (?token=) and widget
            // (data-auth) sign-in tokens; one secret per agent so regenerating
            // only invalidates that agent's links.
            $table->text('auth_secret')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
