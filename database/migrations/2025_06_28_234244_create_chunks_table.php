<?php

use App\Workspace\Models\User;
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

            $table->enum('type', ['text', 'image', 'table']);

            $table->unsignedInteger('chunk_index');
            $table->text('chunk');
            $table->text('summary')->nullable();
            $table->boolean('is_enhanced')->default(false);
            $table->string('chunked_using_model')->nullable();
            $table->string('embedding_model')->nullable();
            $table->unsignedBigInteger('cost_in_micro_cents')->default(0);
            $table->unsignedInteger('tokens');

            $table->vector('embedding', 1024);
            $table->bm25vector('embedding_bm25');

            $table->text('questions')->nullable();
            $table->vector('questions_embedding', 1024)->nullable();
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
    autovacuum_analyze_scale_factor = 0.02,
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
        $buildThreads = (int) config('vector.build_threads', 4);

        $options = <<<EOT
residual_quantization = true
[build.internal]
lists = [1000]
build_threads = {$buildThreads}
spherical_centroids = false
sampling_factor = 256
kmeans_iterations = 25

EOT;

        DB::statement("CREATE INDEX {$indexName} ON chunks USING vchordrq ({$column} vector_l2_ops) WITH (options = '{$options}')");
    }
};
