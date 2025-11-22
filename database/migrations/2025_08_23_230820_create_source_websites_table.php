<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_websites', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('language')->nullable();
            $table->string('document_classification')->nullable();
            $table->string('url');
            $table->string('checksum')->index()->nullable();
            $table->jsonb('options')->nullable();
            $table->unsignedInteger('expected_chunks')->nullable();
            $table->string('internal_ingestion_job_id')->unique()->nullable();
            $table->string('internal_chunking_batch_id')->unique()->nullable();
            $table->enum('status', ['pending', 'crawling', 'crawled', 'ingesting', 'failed', 'ingested'])->default('pending')->index();
            $table->unsignedInteger('took_in_seconds')->default(0);
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_websites');
    }
};
