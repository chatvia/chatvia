<?php

use Domain\Llm\Models\Llm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llms', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('llm_provider_id')->constrained('llm_providers')->cascadeOnDelete();

            $table->string('name');

            $table->jsonb('capabilities')->nullable();

            $table->boolean('is_enabled')->default(true);

            $table->boolean('is_reasoning_enabled')->default(true);

            $table->timestamp('unlisted_at')->nullable();

            $table->timestamps();

            $table->unique(['llm_provider_id', 'name']);
        });

        Llm::flushCatalogCache();
    }

    public function down(): void
    {
        Schema::dropIfExists('llms');
    }
};
