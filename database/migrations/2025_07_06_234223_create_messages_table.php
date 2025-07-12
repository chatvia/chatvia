<?php

use App\Models\Agent;
use App\Models\Member;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id')->index();
            $table->string('generation_id')->nullable();

            $table->foreignIdFor(Agent::class)->constrained()->nullOnDelete();
            $table->foreignIdFor(Member::class)->nullable()->constrained()->nullOnDelete();

            $table->text('content');

            $table->enum('role', ['user', 'assistant'])->index();
            $table->string('model')->nullable();

            $table->enum('feedback', ['positive', 'negative'])->nullable();
            $table->bigInteger('response_time_ms')->nullable();
            $table->integer('token_count')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->timestamp('expires_at')->nullable();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['agent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
