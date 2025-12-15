<?php

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

            // Identifiers
            $table->string('internal_name')->unique()->index();
            $table->string('external_name')->unique()->index();

            $table->unsignedInteger('credits');

            // Display information
            $table->string('descriptive_name'); // Maps to config 'name'
            $table->text('description'); // Changed to text for longer descriptions
            $table->string('icon');

            // Technical specifications
            $table->unsignedInteger('context_window'); // Maps to config 'context' - token limit
            $table->jsonb('features'); // Features like ['fast', 'advanced', 'pro']
            $table->enum('location', ['us', 'eu', 'cn'])->index();
            $table->boolean('reasoning')->default(false);

            $table->decimal('input_price_per_1m_tokens', 10, 4)->nullable();
            $table->decimal('output_price_per_1m_tokens', 10, 4)->nullable();

            $table->boolean('is_default')->default(false);

            $table->timestamps();
        });

        // Load LLM models from JSON file
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
                        'reasoning' => false,
                        'credits' => $llmData['credits'] ?? 1,
                        'input_price_per_1m_tokens' => $llmData['input_price_per_1m_tokens'] ?? null,
                        'output_price_per_1m_tokens' => $llmData['output_price_per_1m_tokens'] ?? null,
                        'is_default' => $llmData['is_default'] ?? false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                });

                DB::table('llms')->insert($dataToInsert);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('llms');
    }
};
