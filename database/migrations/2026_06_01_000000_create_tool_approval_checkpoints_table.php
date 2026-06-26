<?php

use Domain\Agent\Models\Agent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_approval_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignIdFor(Agent::class)->constrained()->cascadeOnDelete();

            $table->uuid('conversation_external_id');
            $table->uuid('assistant_message_id');
            $table->string('request_id')->nullable();

            $table->unsignedSmallInteger('iteration')->default(0);

            $table->enum('status', ['pending', 'resolved', 'expired'])->default('pending');

            $table->jsonb('messages_snapshot');

            $table->jsonb('pending_calls');

            $table->jsonb('context');

            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_external_id', 'status']);
            $table->index('agent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_approval_checkpoints');
    }
};
