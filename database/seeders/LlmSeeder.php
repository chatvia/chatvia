<?php

namespace Database\Seeders;

use Domain\Agents\Models\Llm;
use Illuminate\Database\Seeder;

class LlmSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding LLM models...');

        // Load LLM models from JSON file
        $jsonPath = database_path('data/llms.json');

        if (! file_exists($jsonPath)) {
            $this->command->error('LLM data file not found at: '.$jsonPath);

            return;
        }

        $llms = json_decode(file_get_contents($jsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error('Failed to parse LLM data file: '.json_last_error_msg());

            return;
        }

        foreach ($llms as $llm) {
            // Add default values for features and reasoning
            $llm['features'] = [];
            $llm['reasoning'] = false;

            Llm::updateOrCreate(
                ['internal_name' => $llm['internal_name']],
                $llm
            );
        }

        $this->command->info('Created '.count($llms).' LLM models.');
    }
}
