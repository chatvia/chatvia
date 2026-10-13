<?php

use Domain\Knowledge\Ingestion\Models\Chunk;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The knowledge an assistant answer was given, best first: which chunk of
 * which source, how relevant the search found it (the reranker's score, or
 * the similarity check's), and how the source is shown (label, page link,
 * page number). Kept with the answer so a bad answer can be traced to the
 * chunks behind it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_message_sources', function (Blueprint $table): void {
            $table->foreignId('workspace_id');
            $table->foreignId('chat_message_id');
            $table->unsignedTinyInteger('rank');

            $table->foreignIdFor(Chunk::class)->nullable()->constrained()->nullOnDelete();
            $table->string('sourceable_type', 40);
            $table->unsignedBigInteger('sourceable_id');

            $table->decimal('score', 5, 4)->nullable();
            $table->string('label', 500);
            $table->string('url', 2048)->nullable();
            $table->unsignedInteger('page')->nullable();

            $table->primary(['chat_message_id', 'rank']);
            $table->foreign(['workspace_id', 'chat_message_id'])->references(['workspace_id', 'id'])->on('chat_messages')->cascadeOnDelete();
            $table->index('chunk_id');
            $table->index(['sourceable_type', 'sourceable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_sources');
    }
};
