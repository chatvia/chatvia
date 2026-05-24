<?php

use Domain\Ingestion\Models\Chunk;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chunk_references', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Chunk::class)->constrained()->cascadeOnDelete();
            $table->morphs('referenceable');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chunk_references');
    }
};
