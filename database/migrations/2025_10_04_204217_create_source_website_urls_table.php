<?php

use Domain\Knowledge\Source\Web\Models\SourceWebsite;
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
            // The page's title as the site gives it, shown as the source of an answer.
            $table->string('title', 500)->nullable();
            $table->string('checksum')->nullable();
            $table->enum('status', ['pending', 'ingesting', 'ingested', 'failed'])->default('pending');
            $table->text('failure_reason')->nullable();
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
