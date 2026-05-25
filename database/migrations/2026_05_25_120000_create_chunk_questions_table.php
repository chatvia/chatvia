<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chunk_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chunk_id')->constrained('chunks')->cascadeOnDelete();
            $table->text('question');
            $table->vector('embedding', 1024);
            $table->timestamps();
        });

        $this->createOptimizedVectorChordIndex();

        DB::statement('ANALYZE chunk_questions');
    }

    public function down(): void
    {
        Schema::dropIfExists('chunk_questions');
    }

    private function createOptimizedVectorChordIndex(): void
    {
        $buildThreads = min(8, (int) shell_exec('nproc 2>/dev/null') ?: 4);

        $indexOptions = <<<EOT
residual_quantization = true
[build.internal]
lists = [1024]
build_threads = {$buildThreads}
spherical_centroids = false
sampling_factor = 512
kmeans_iterations = 50

EOT;

        DB::statement("
CREATE INDEX chunk_questions_embedding_vchordrq
ON chunk_questions
USING vchordrq (embedding vector_l2_ops)
WITH (options = '{$indexOptions}');
        ");
    }
};
