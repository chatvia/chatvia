<?php

use App\Models\Agent;
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
            $table->foreignIdFor(Agent::class)->index()->constrained()->cascadeOnDelete();

            $table->morphs('chunkable');

            $table->enum('type', ['text', 'image', 'table']);
            $table->unsignedInteger('chunk_index');
            $table->text('chunk');
            $table->string('chunked_using_model')->nullable();
            $table->decimal('cost_in_cents', 10, 3)->default(0);
            $table->unsignedInteger('tokens');
            $table->vector('embedding', 1536);
            $table->bm25vector('embedding_bm25');
            $table->jsonb('metadata')->nullable();
            $table->string('checksum');
            $table->unsignedInteger('retrieval_count')->default(0);
            $table->timestamps();

            $table->unique(['chunkable_type', 'chunkable_id', 'checksum']);
        });

        DB::statement('CREATE INDEX chunks_embedding_bm25 ON chunks USING bm25 (embedding_bm25 bm25_catalog.bm25_ops);');

        $this->createOptimizedVectorChordIndex();

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
};
