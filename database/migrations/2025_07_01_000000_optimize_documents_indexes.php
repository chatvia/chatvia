<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add agent_id index for faster filtering (non-concurrent for simplicity)
        DB::statement('CREATE INDEX IF NOT EXISTS documents_agent_id_idx ON documents (agent_id)');

        // Add md5_hash btree index for deduplication
        DB::statement('CREATE INDEX IF NOT EXISTS documents_md5_hash_idx ON documents (md5_hash)');

        // Optimize existing binary quantization index with better parameters
        DB::statement('DROP INDEX IF EXISTS documents_embedding_bit_hamming_idx');

        DB::statement('
        CREATE INDEX documents_embedding_bit_hamming_optimized_idx
        ON documents
        USING hnsw ((binary_quantize(embedding)::bit(1536)) bit_hamming_ops)
        WITH (m = 24, ef_construction = 400)
        ');

        // Update table statistics
        DB::statement('ANALYZE documents');

        DB::statement('CREATE INDEX ON documents USING hnsw (embedding vector_cosine_ops) WITH (m = 16, ef_construction = 200)');

        DB::statement('
        CREATE INDEX documents_embedding_bit_hamming_idx
        ON documents
        USING hnsw ((binary_quantize(embedding)::bit(1536)) bit_hamming_ops)
        WITH (m = 16, ef_construction = 200)
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS documents_agent_id_idx');
        DB::statement('DROP INDEX IF EXISTS documents_embedding_bit_hamming_optimized_idx');
        DB::statement('DROP INDEX IF EXISTS documents_md5_hash_idx');

        // Recreate original binary index
        DB::statement('
        CREATE INDEX documents_embedding_bit_hamming_idx
        ON documents
        USING hnsw ((binary_quantize(embedding)::bit(1536)) bit_hamming_ops)
        WITH (m = 16, ef_construction = 200)
        ');
    }
};
