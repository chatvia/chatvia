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
            $table->foreignIdFor(Agent::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(AgentFile::class)->constrained()->cascadeOnDelete();
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
            $table->timestamps();

            $table->unique(['agent_file_id', 'md5_hash']);
        });

        DB::statement('CREATE INDEX documents_embedding_bm25 ON documents USING bm25 (embedding_bm25 bm25_catalog.bm25_ops);');
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
