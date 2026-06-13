<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_websites', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('language')->nullable();
            $table->string('document_classification')->nullable();
            $table->string('url');
            $table->string('filepath')->unique()->nullable();
            $table->string('checksum')->nullable();
            $table->jsonb('options')->nullable();
            $table->unsignedInteger('expected_chunks')->nullable();
            $table->string('internal_ingestion_job_id')->unique()->nullable();
            $table->string('internal_chunking_batch_id')->unique()->nullable();
            $table->enum('status', ['pending', 'crawling', 'crawled', 'ingesting', 'failed', 'ingested'])->default('pending');
            $table->unsignedInteger('took_in_seconds')->default(0);
            $table->text('failure_reason')->nullable();
            $table->unsignedInteger('retrieval_count')->default(0);
            $table->timestamp('last_retrieved_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'url']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_websites');
    }
};
