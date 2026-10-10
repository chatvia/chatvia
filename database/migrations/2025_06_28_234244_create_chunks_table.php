<?php

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
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

            $table->uuid('external_id')->default(DB::raw('uuidv7()'))->unique();
            $table->foreignIdFor(User::class)->index()->constrained()->cascadeOnDelete();

            $table->string('chunkable_type');
            $table->unsignedBigInteger('chunkable_id');

            $table->unsignedInteger('chunk_index');
            $table->text('chunk');
            $table->text('summary')->nullable();
            $table->boolean('is_enhanced')->default(false);
            $table->string('chunked_using_model')->nullable();
            $table->unsignedBigInteger('cost_in_micro_cents')->default(0);
            $table->unsignedInteger('tokens');

            $table->embedding('embedding');
            $table->bm25vector('embedding_bm25');

            $table->text('questions')->nullable();
            $table->embedding('questions_embedding')->nullable();
            $table->bm25vector('questions_embedding_bm25')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->string('checksum');
            $table->timestamps();

            $table->unique(['chunkable_type', 'chunkable_id', 'checksum']);

            $table->index(['chunkable_type', 'chunkable_id', 'chunk_index'], 'chunks_page_idx');
        });

        DB::statement('CREATE INDEX chunks_embedding_bm25 ON chunks USING bm25 (embedding_bm25 bm25_catalog.bm25_ops)');
        DB::statement('CREATE INDEX chunks_questions_embedding_bm25 ON chunks USING bm25 (questions_embedding_bm25 bm25_catalog.bm25_ops)');

        $this->createVectorChordIndex('chunks_embedding_vchordrq_optimized', 'embedding');
        $this->createVectorChordIndex('chunks_questions_embedding_vchordrq', 'questions_embedding');

        DB::statement('CREATE INDEX chunks_metadata_gin_idx ON chunks USING GIN (metadata)');

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

    private function createVectorChordIndex(string $indexName, string $column): void
    {
        $buildThreads = (int) config('chatvia.vector.build_threads', 4);

        $options = <<<EOT
residual_quantization = true
[build.internal]
lists = [4096]
build_threads = {$buildThreads}
spherical_centroids = false
sampling_factor = 256
kmeans_iterations = 25

EOT;

        // The install's vector type decides the operator class (EmbeddingStorage).
        $ops = app(EmbeddingStorage::class)->type()->operatorClass();

        DB::statement("CREATE INDEX {$indexName} ON chunks USING vchordrq ({$column} {$ops}) WITH (options = '{$options}')");
    }
};
