<?php

use App\Workspace\Models\User;
use App\Team\Models\Team;
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
            $table->foreignIdFor(User::class)->index()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Team::class)->index()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sub_domain')->nullable()->unique();
            $table->string('domain')->nullable()->unique();
            $table->text('system_instructions')->nullable();
            $table->foreignId('agent_prompt_id')->nullable()->index()->constrained('agent_prompts')->nullOnDelete();

            $table->foreignId('llm_id')->nullable()->index()->constrained('llms')->nullOnDelete();
            $table->boolean('is_public')->default(false);
            $table->jsonb('metadata')->nullable();
            $table->enum('source', ['dashboard', 'api'])->default('dashboard')->index();

            $table->text('auth_secret')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
