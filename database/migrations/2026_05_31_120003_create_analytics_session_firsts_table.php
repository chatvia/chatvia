<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('analytics.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('analytics_session_firsts', function (Blueprint $table): void {
            $table->unsignedBigInteger('agent_id');
            $table->unsignedBigInteger('user_id');
            $table->uuid('session_external_id');
            $table->timestampTz('first_seen_at');

            $table->primary(['agent_id', 'session_external_id']);
            $table->index(['agent_id', 'first_seen_at']);
            $table->index(['user_id', 'first_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('analytics_session_firsts');
    }
};
