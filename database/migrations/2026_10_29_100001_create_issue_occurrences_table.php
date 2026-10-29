<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_occurrences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable();
            $table->enum('actor_kind', ['staff', 'chatter', 'api_key', 'visitor', 'system']);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('chatter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained('personal_access_tokens')->nullOnDelete();
            $table->string('model')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('endpoint')->nullable();
            $table->text('message')->nullable();
            $table->enum('origin_kind', ['request', 'job', 'command']);
            $table->string('origin_name')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['issue_id', 'occurred_at']);
            $table->index('occurred_at');
            $table->index('user_id');
            $table->index('chatter_id');

            $table->foreign(['workspace_id', 'agent_id'], 'issue_occurrences_workspace_agent_foreign')
                ->references(['workspace_id', 'id'])
                ->on('agents')
                ->onDelete('set null (agent_id)');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_occurrences');
    }
};
