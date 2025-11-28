<?php

namespace Database\Seeders;

use App\Models\Llm;
use Illuminate\Database\Seeder;

class LlmSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding LLM models...');

        $llms = [
            [
                'internal_name' => 'gpt-4o',
                'external_name' => 'GPT-4o',
                'descriptive_name' => 'GPT-4o',
                'description' => 'OpenAI\'s most advanced multimodal model. Excels at text, vision, and reasoning tasks.',
                'icon' => 'gpt.svg',
                'context_window' => 128000,
                'features' => ['multimodal' => true, 'function_calling' => true, 'streaming' => true, 'vision' => true],
                'location' => 'us',
                'reasoning' => true,
                'requires_paid_plan' => true,
                'input_price_per_1m_tokens' => 5.00,
                'output_price_per_1m_tokens' => 15.00,
                'is_default' => true,
            ],
            [
                'internal_name' => 'gpt-4o-mini',
                'external_name' => 'GPT-4o Mini',
                'descriptive_name' => 'GPT-4o Mini',
                'description' => 'Fast and affordable model for everyday tasks. Great balance of speed and capability.',
                'icon' => 'gpt.svg',
                'context_window' => 128000,
                'features' => ['multimodal' => true, 'function_calling' => true, 'streaming' => true, 'vision' => true],
                'location' => 'us',
                'reasoning' => false,
                'requires_paid_plan' => false,
                'input_price_per_1m_tokens' => 0.15,
                'output_price_per_1m_tokens' => 0.60,
                'is_default' => false,
            ],
            [
                'internal_name' => 'claude-3-5-sonnet-20241022',
                'external_name' => 'Claude 3.5 Sonnet',
                'descriptive_name' => 'Claude 3.5 Sonnet',
                'description' => 'Anthropic\'s most intelligent model. Excellent for complex analysis and creative tasks.',
                'icon' => 'claude.svg',
                'context_window' => 200000,
                'features' => ['multimodal' => true, 'function_calling' => true, 'streaming' => true, 'vision' => true],
                'location' => 'us',
                'reasoning' => true,
                'requires_paid_plan' => true,
                'input_price_per_1m_tokens' => 3.00,
                'output_price_per_1m_tokens' => 15.00,
                'is_default' => false,
            ],
            [
                'internal_name' => 'claude-3-haiku-20240307',
                'external_name' => 'Claude 3 Haiku',
                'descriptive_name' => 'Claude 3 Haiku',
                'description' => 'Anthropic\'s fastest and most affordable model. Ideal for quick responses.',
                'icon' => 'claude.svg',
                'context_window' => 200000,
                'features' => ['multimodal' => true, 'function_calling' => true, 'streaming' => true, 'vision' => true],
                'location' => 'us',
                'reasoning' => false,
                'requires_paid_plan' => false,
                'input_price_per_1m_tokens' => 0.25,
                'output_price_per_1m_tokens' => 1.25,
                'is_default' => false,
            ],
            [
                'internal_name' => 'gemini-1.5-pro',
                'external_name' => 'Gemini 1.5 Pro',
                'descriptive_name' => 'Gemini 1.5 Pro',
                'description' => 'Google\'s most capable model with massive context window. Great for long documents.',
                'icon' => 'gemini.svg',
                'context_window' => 1000000,
                'features' => ['multimodal' => true, 'function_calling' => true, 'streaming' => true, 'vision' => true],
                'location' => 'us',
                'reasoning' => true,
                'requires_paid_plan' => true,
                'input_price_per_1m_tokens' => 3.50,
                'output_price_per_1m_tokens' => 10.50,
                'is_default' => false,
            ],
            [
                'internal_name' => 'gemini-1.5-flash',
                'external_name' => 'Gemini 1.5 Flash',
                'descriptive_name' => 'Gemini 1.5 Flash',
                'description' => 'Google\'s fast and efficient model. Perfect for high-volume tasks.',
                'icon' => 'gemini.svg',
                'context_window' => 1000000,
                'features' => ['multimodal' => true, 'function_calling' => true, 'streaming' => true, 'vision' => true],
                'location' => 'us',
                'reasoning' => false,
                'requires_paid_plan' => false,
                'input_price_per_1m_tokens' => 0.075,
                'output_price_per_1m_tokens' => 0.30,
                'is_default' => false,
            ],
            [
                'internal_name' => 'llama-3.1-70b',
                'external_name' => 'Llama 3.1 70B',
                'descriptive_name' => 'Llama 3.1 70B',
                'description' => 'Meta\'s powerful open-source model. Great for a wide range of tasks.',
                'icon' => 'llama.svg',
                'context_window' => 128000,
                'features' => ['multimodal' => false, 'function_calling' => true, 'streaming' => true, 'vision' => false],
                'location' => 'us',
                'reasoning' => true,
                'requires_paid_plan' => false,
                'input_price_per_1m_tokens' => 0.35,
                'output_price_per_1m_tokens' => 0.40,
                'is_default' => false,
            ],
            [
                'internal_name' => 'llama-3.1-8b',
                'external_name' => 'Llama 3.1 8B',
                'descriptive_name' => 'Llama 3.1 8B',
                'description' => 'Meta\'s efficient smaller model. Fast and cost-effective.',
                'icon' => 'llama.svg',
                'context_window' => 128000,
                'features' => ['multimodal' => false, 'function_calling' => true, 'streaming' => true, 'vision' => false],
                'location' => 'us',
                'reasoning' => false,
                'requires_paid_plan' => false,
                'input_price_per_1m_tokens' => 0.05,
                'output_price_per_1m_tokens' => 0.08,
                'is_default' => false,
            ],
        ];

        foreach ($llms as $llm) {
            Llm::updateOrCreate(
                ['internal_name' => $llm['internal_name']],
                $llm
            );
        }

        $this->command->info('Created '.count($llms).' LLM models.');
    }
}
