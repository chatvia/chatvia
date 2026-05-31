<?php

use Domain\Sources\Web\Models\SourceWebsite;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_website_urls', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(SourceWebsite::class)->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->text('text')->nullable();
            $table->string('checksum')->nullable();
            $table->enum('status', ['pending', 'ingesting', 'ingested', 'failed'])->index()->default('pending');
            $table->string('internal_ingestion_job_id')->unique()->nullable();
            $table->string('internal_chunking_batch_id')->unique()->nullable();
            $table->text('failure_reason')->nullable();
            $table->unsignedInteger('retrieval_count')->default(0);
            $table->timestamp('crawled_at')->nullable();
            $table->timestamps();

            $table->index(['source_website_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_website_urls');
    }
};
