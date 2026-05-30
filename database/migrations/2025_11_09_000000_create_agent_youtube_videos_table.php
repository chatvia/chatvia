<?php

use App\Auth\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('source_youtube_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('language')->nullable();
            $table->string('document_classification')->nullable();
            $table->string('url');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('checksum')->nullable();
            $table->string('parser')->nullable();
            $table->string('parser_filepath')->nullable();
            $table->unsignedInteger('expected_chunks')->nullable();
            $table->unsignedInteger('length')->default(0);
            $table->string('internal_ingestion_job_id')->nullable();
            $table->string('internal_chunking_batch_id')->nullable();
            $table->string('external_parsing_job_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->enum('status', ['uploaded', 'pending', 'ingesting', 'failed', 'ingested'])->default('pending');
            $table->unsignedInteger('took_in_seconds')->default(0);
            $table->text('failure_reason')->nullable();
            $table->unsignedInteger('retrieval_count')->default(0);
            $table->timestamp('last_retrieved_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'checksum']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_youtube_videos');
    }
};
