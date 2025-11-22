<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_files', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('language')->nullable();
            $table->string('document_classification')->nullable();
            $table->string('filename');
            $table->enum('filetype', ['pdf', 'word', 'image', 'video', 'audio', 'spreadsheet', 'presentation', 'text', 'markdown'])->index();
            $table->string('filepath')->unique();
            $table->decimal('size_in_kb');
            $table->string('mime_type')->index();
            $table->string('checksum')->index();
            $table->string('parser')->nullable();
            $table->string('parser_filepath')->nullable()->unique();
            $table->unsignedInteger('expected_chunks')->nullable();
            $table->unsignedInteger('length')->default(0);
            $table->string('internal_ingestion_job_id')->unique()->nullable();
            $table->string('internal_chunking_batch_id')->unique()->nullable();
            $table->string('external_parsing_job_id')->unique()->nullable();
            $table->jsonb('metadata')->index()->nullable();
            $table->unsignedInteger('retrieval_count')->default(0);
            $table->enum('status', ['uploaded', 'pending', 'ingesting', 'failed', 'ingested'])->default('pending')->index();
            $table->timestamps();

            $table->unique(['user_id', 'checksum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_files');
    }
};
