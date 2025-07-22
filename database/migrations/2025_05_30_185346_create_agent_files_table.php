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
            $table->string('language_code')->nullable();
            $table->string('name');
            $table->string('path')->unique();
            $table->decimal('size_in_kb');
            $table->string('mime_type');
            $table->string('md5_hash');
            $table->unsignedInteger('chunks')->nullable();
            $table->unsignedInteger('number_of_pages')->default(0);
            $table->unsignedInteger('minutes')->default(0);
            $table->enum('status', ['uploaded', 'pending', 'processing', 'failed', 'completed'])->default('pending')->index();
            $table->string('external_ingestion_job_id')->unique()->nullable();
            $table->string('document_type')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('retrieval_count')->default(0);
            $table->timestamps();

            $table->unique(['agent_id', 'md5_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_files');
    }
};
