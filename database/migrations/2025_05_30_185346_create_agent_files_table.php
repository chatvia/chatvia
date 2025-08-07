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
        Schema::create('agent_files', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Agent::class)->constrained()->cascadeOnDelete();
            $table->string('language')->nullable();
            $table->string('document_type')->nullable();
            $table->string('filename');
            $table->string('filepath')->unique();
            $table->decimal('size_in_kb');
            $table->string('mime_type')->index();
            $table->string('md5_hash');
            $table->string('parser')->nullable();
            $table->string('parser_filepath')->nullable()->unique();
            $table->unsignedInteger('chunks')->nullable();
            $table->unsignedInteger('length')->default(0);
            $table->string('internal_ingestion_job_id')->unique()->nullable();
            $table->string('internal_chunking_batch_id')->unique()->nullable();
            $table->string('external_parsing_job_id')->unique()->nullable();
            $table->jsonb('metadata')->index()->nullable();
            $table->unsignedInteger('retrieval_count')->default(0);
            $table->enum('status', ['uploaded', 'pending', 'processing', 'failed', 'completed'])->default('pending')->index();
            $table->timestamps();

            $table->unique(['agent_id', 'md5_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_files');
    }
};
