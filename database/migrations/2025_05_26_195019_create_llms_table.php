<?php

use Domain\Agents\Models\Llm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llms', function (Blueprint $table) {
            $table->id();

            $table->string('internal_name')->unique();
            $table->string('external_name')->unique();

            $table->unsignedInteger('credits');

            $table->string('descriptive_name');
            $table->text('description');
            $table->string('icon');

            $table->unsignedInteger('context_window');
            $table->jsonb('features');
            $table->enum('location', ['us', 'eu', 'cn']);
            $table->boolean('is_reasoning')->default(false);

            $table->decimal('input_price_per_1m_tokens', 10, 4)->nullable();
            $table->decimal('output_price_per_1m_tokens', 10, 4)->nullable();

            $table->enum('provider', ['scaleway', 'openai', 'anthropic', 'mistral', 'google'])->index();

            $table->timestamps();
        });

        $jsonPath = database_path('data/llms.json');

        if (file_exists($jsonPath)) {
            $llms = json_decode(file_get_contents($jsonPath), true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $dataToInsert = [];

                collect($llms)->each(function ($llmData) use (&$dataToInsert) {
                    $dataToInsert[] = [
                        'internal_name' => $llmData['internal_name'],
                        'external_name' => $llmData['external_name'],
                        'descriptive_name' => $llmData['descriptive_name'],
                        'description' => $llmData['description'],
                        'icon' => $llmData['icon'],
                        'context_window' => $llmData['context_window'],
                        'features' => json_encode([]),
                        'location' => $llmData['location'],
                        'is_reasoning' => $llmData['reasoning'] ?? false,
                        'credits' => $llmData['credits'] ?? 1,
                        'input_price_per_1m_tokens' => $llmData['input_price_per_1m_tokens'] ?? null,
                        'output_price_per_1m_tokens' => $llmData['output_price_per_1m_tokens'] ?? null,
                        'provider' => $llmData['provider'] ?? 'scaleway',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                });

                DB::table('llms')->insert($dataToInsert);
            }
        }

        Llm::flushCatalogCache();
    }

    public function down(): void
    {
        Schema::dropIfExists('llms');
    }
};
