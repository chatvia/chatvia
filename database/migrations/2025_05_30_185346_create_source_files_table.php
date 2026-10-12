<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_files', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('language')->nullable();
            $table->string('document_classification')->nullable();
            $table->string('filename');
            $table->enum('filetype', ['pdf', 'word', 'image', 'video', 'audio', 'spreadsheet', 'presentation', 'text', 'markdown', 'document']);
            $table->string('filepath')->unique();
            $table->unsignedBigInteger('size_in_kb');
            $table->string('mime_type');
            $table->string('checksum');
            $table->string('parser')->nullable();
            $table->string('parser_filepath')->nullable()->unique();
            $table->unsignedInteger('expected_chunks')->nullable();
            $table->unsignedInteger('length')->default(0);
            $table->string('internal_ingestion_job_id')->unique()->nullable();
            $table->string('internal_chunking_batch_id')->unique()->nullable();
            $table->string('external_parsing_job_id')->unique()->nullable();
            $table->jsonb('metadata')->nullable();
            $table->enum('status', ['uploaded', 'pending', 'ingesting', 'failed', 'ingested'])->default('pending');
            $table->unsignedInteger('took_in_seconds')->default(0);
            $table->text('failure_reason')->nullable();
            $table->unsignedBigInteger('retrieval_count')->default(0);
            $table->timestamp('last_retrieved_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'checksum']);

            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_files');
    }
};
