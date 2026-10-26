<?php

use Domain\Knowledge\Retrieval\Actions\ConfigureLexicalIndexesAction;
use Domain\Knowledge\Retrieval\Actions\ConfigureVectorIndexesAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chunks', function (Blueprint $table): void {
            $table->id();

            $table->uuid('external_id')->default(DB::raw('uuidv7()'))->unique();
            $table->foreignId('workspace_id')->index()->constrained()->cascadeOnDelete();

            $table->string('chunkable_type');
            $table->unsignedBigInteger('chunkable_id');

            $table->unsignedInteger('chunk_index');
            $table->text('chunk');
            $table->text('summary')->nullable();
            $table->boolean('is_enhanced')->default(false);
            $table->string('chunked_using_model')->nullable();
            $table->unsignedInteger('tokens');

            $table->embedding('embedding');
            // What the embedding was computed from (summary + content + visual
            // descriptions); the lexical channels search it.
            $table->text('search_text');

            $table->text('questions')->nullable();
            $table->embedding('questions_embedding')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->string('checksum');
            $table->timestamps();

            $table->unique(['chunkable_type', 'chunkable_id', 'checksum']);

            $table->index(['chunkable_type', 'chunkable_id', 'chunk_index', 'id'], 'chunks_page_idx');
        });

        // The lexical (keyword) indexes, in the shape of the install's driver.
        app(ConfigureLexicalIndexesAction::class)->execute('chunks');

        app(ConfigureVectorIndexesAction::class)->execute();

        DB::statement(<<<'SQL'
ALTER TABLE chunks SET (
    autovacuum_analyze_scale_factor = 0.01,
    autovacuum_analyze_threshold    = 1000,
    autovacuum_vacuum_scale_factor  = 0.05
)
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('chunks');
    }
};
