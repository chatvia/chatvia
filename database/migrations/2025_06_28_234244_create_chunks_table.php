<?php

use App\Auth\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chunks', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique()->nullable();
            $table->foreignIdFor(User::class)->index()->constrained()->cascadeOnDelete();

            // The parent model this chunk belongs to
            $table->morphs('chunkable');

            $table->enum('type', ['text', 'image', 'table']);

            $table->unsignedInteger('chunk_index');
            $table->text('chunk');
            $table->boolean('enhanced')->default(false);
            $table->string('chunked_using_model')->nullable();
            $table->string('embedding_model')->nullable();
            $table->text('summary')->nullable()->after('chunk');
            $table->decimal('cost_in_cents', 10, 3)->default(0);
            $table->unsignedInteger('tokens');

            $table->vector('embedding', 1024);
            $table->bm25vector('embedding_bm25');

            $table->text('questions')->nullable();
            $table->vector('questions_embedding', 1024)->nullable();
            $table->bm25vector('questions_embedding_bm25')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->string('checksum');
            $table->unsignedInteger('retrieval_count')->default(0);
            $table->timestamp('last_retrieved_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['chunkable_type', 'chunkable_id', 'checksum']);

        });

        DB::statement('CREATE INDEX chunks_embedding_bm25 ON chunks USING bm25 (embedding_bm25 bm25_catalog.bm25_ops);');
        DB::statement('CREATE INDEX chunks_questions_embedding_bm25 ON chunks USING bm25 (questions_embedding_bm25 bm25_catalog.bm25_ops);');
        $this->createOptimizedVectorChordIndex();
        $this->createOptimizedQuestionsVectorChordIndex();

        // GIN index on the metadata JSONB column — accelerates queries that
        // filter by metadata keys (e.g. retrieval scoping by source type).
        DB::statement('CREATE INDEX chunks_metadata_gin_idx ON chunks USING GIN (metadata)');

        // Update table statistics for optimal query planning
        DB::statement('ANALYZE chunks');
    }

    public function down(): void
    {
        Schema::dropIfExists('chunks');
    }

    /**
     * Create optimized VectorChord index using best practices
     */
    private function createOptimizedVectorChordIndex(): void
    {
        $buildThreads = min(8, (int) shell_exec('nproc 2>/dev/null') ?: 4);

        $indexOptions = <<<EOT
residual_quantization = true
[build.internal]
lists = [4096]
build_threads = {$buildThreads}
spherical_centroids = false
sampling_factor = 512
kmeans_iterations = 50

EOT;

        // Create the index with optimized configuration
        DB::statement("
CREATE INDEX chunks_embedding_vchordrq_optimized
ON chunks
USING vchordrq (embedding vector_l2_ops)
WITH (options = '{$indexOptions}');
        ");
    }

    /**
     * Same VectorChord config as above but for the questions vector. Fewer
     * non-null rows than the main embedding (only enhanced chunks have it),
     * but we keep the index large since lookup speed beats build cost.
     */
    private function createOptimizedQuestionsVectorChordIndex(): void
    {
        $buildThreads = min(8, (int) shell_exec('nproc 2>/dev/null') ?: 4);

        $indexOptions = <<<EOT
residual_quantization = true
[build.internal]
lists = [4096]
build_threads = {$buildThreads}
spherical_centroids = false
sampling_factor = 512
kmeans_iterations = 50

EOT;

        DB::statement("
CREATE INDEX chunks_questions_embedding_vchordrq
ON chunks
USING vchordrq (questions_embedding vector_l2_ops)
WITH (options = '{$indexOptions}');
        ");
    }
};
