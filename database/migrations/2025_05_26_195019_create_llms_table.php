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

            // The model's id at its provider (sent in every call), and the
            // stable alias agents, settings and analytics refer to it by.
            $table->string('internal_name');
            $table->string('external_name')->unique();

            $table->string('descriptive_name');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();

            // Null when the provider does not say.
            $table->unsignedInteger('context_window')->nullable();
            $table->jsonb('features')->default('{}');
            $table->enum('location', ['us', 'eu', 'cn'])->nullable();
            $table->boolean('is_reasoning')->default(false);

            // Catalog prices in integer micro-cents per million tokens
            // (1 dollar = 10^8 micro-cents), like chunks.cost_in_micro_cents.
            // Null when the provider does not say.
            $table->unsignedBigInteger('input_price_per_1m_tokens_in_micro_cents')->nullable();
            $table->unsignedBigInteger('output_price_per_1m_tokens_in_micro_cents')->nullable();

            // A disabled model is hidden from the pickers; the agents that
            // already use it keep it.
            $table->boolean('is_enabled')->default(true);

            $table->timestamps();

            $table->unique(['llm_provider_id', 'internal_name']);
        });

        Llm::flushCatalogCache();
    }

    public function down(): void
    {
        Schema::dropIfExists('llms');
    }
};
