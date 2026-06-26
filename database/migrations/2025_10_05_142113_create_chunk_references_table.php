<?php

use Domain\Knowledge\Ingestion\Models\Chunk;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chunk_references', function (Blueprint $table) {
            $table->foreignIdFor(Chunk::class)->constrained()->cascadeOnDelete();

            $table->numericMorphs('referenceable');

            $table->primary(['chunk_id', 'referenceable_type', 'referenceable_id'], 'chunk_references_pkey');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chunk_references');
    }
};
