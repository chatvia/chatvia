<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('chatvia.analytics.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('analytics_session_firsts', function (Blueprint $table): void {
            $table->unsignedBigInteger('agent_id');
            $table->unsignedBigInteger('workspace_id');
            $table->uuid('session_external_id');
            $table->timestamp('first_seen_at');
            $table->string('device_type')->nullable();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();

            $table->primary(['agent_id', 'session_external_id']);
            $table->index(['agent_id', 'first_seen_at']);
            $table->index(['workspace_id', 'first_seen_at']);
        });

        DB::connection($this->getConnection())->statement('CREATE INDEX analytics_session_firsts_seen_brin ON analytics_session_firsts USING brin (first_seen_at)');
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('analytics_session_firsts');
    }
};
