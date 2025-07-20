<?php

use App\Models\Agent;
use App\Models\AgentFile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Agent::class)->index()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(AgentFile::class)->index()->constrained()->cascadeOnDelete();
            $table->enum('type', ['text', 'image', 'table']);
            $table->unsignedInteger('chunk_index');
            $table->text('chunk');
            $table->string('chunked_using_model')->nullable();
            $table->decimal('cost_in_cents', 10, 3)->default(0);
            $table->unsignedInteger('tokens');
            $table->vector('embedding', 1536);
            $table->bm25vector('embedding_bm25');
            $table->jsonb('metadata')->nullable();
            $table->string('md5_hash');
            $table->unsignedBigInteger('retrieval_count');
            $table->timestamps();

            $table->unique(['agent_file_id', 'md5_hash']);
        });

        DB::statement('CREATE INDEX documents_embedding_bm25 ON documents USING bm25 (embedding_bm25 bm25_catalog.bm25_ops);');

        $this->createOptimizedVectorChordIndex();

        // Update table statistics for optimal query planning
        DB::statement('ANALYZE documents');
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }

    /**
     * Create optimized VectorChord index using best practices
     */
    private function createOptimizedVectorChordIndex(): void
    {
        // Use safe defaults for migration - don't rely on application state
        $residualQuantization = 'true';
        $sphericalCentroids = 'false';
        $buildThreads = min(8, (int) shell_exec('nproc 2>/dev/null') ?: 4);

        // Use a reasonable default for initial index creation
        // This can be optimized later using the optimization commands
        $lists = 1000;

        // Build the optimized index configuration
        $indexOptions = <<<EOT
residual_quantization = {$residualQuantization}
[build.internal]
lists = [{$lists}]
build_threads = {$buildThreads}
spherical_centroids = {$sphericalCentroids}
EOT;

        // Create the index with optimized configuration
        DB::statement("
CREATE INDEX documents_embedding_vchordrq_optimized
ON documents
USING vchordrq (embedding vector_l2_ops)
WITH (options = '{$indexOptions}');
        ");
    }
};
