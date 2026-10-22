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

            // What the model can do, in the models.dev catalog's own words:
            // `reasoning` (does it think), `reasoning_options` (its effort
            // values), `temperature` (does it take sampling settings). From
            // the catalog for a model it lists; for one it does not, only the
            // `reasoning` the admin set. Null when nothing is known.
            $table->jsonb('capabilities')->nullable();

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
