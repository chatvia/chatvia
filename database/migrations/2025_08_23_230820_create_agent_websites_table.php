<?php

use App\Models\Agent;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_websites', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Agent::class)->constrained()->cascadeOnDelete();
            $table->string('language')->nullable();
            $table->string('classification')->nullable();
            $table->string('url');
            $table->jsonb('options')->nullable();
            $table->jsonb('links')->nullable();
            $table->unsignedInteger('chunks')->nullable();
            $table->unsignedInteger('length')->default(0);
            $table->string('internal_ingestion_job_id')->unique()->nullable();
            $table->string('internal_chunking_batch_id')->unique()->nullable();
            $table->enum('status', ['pending', 'crawling', 'crawled', 'ingesting', 'ingested', 'failed'])->default('pending')->index();
            $table->unsignedInteger('took_in_seconds')->default(0);
            $table->timestamps();

            $table->unique(['agent_id', 'url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_websites');
    }
};
