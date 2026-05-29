<?php

namespace App\Hosting;

use App\LLM\Contracts\LLMProviderInterface;
use Domain\Agents\Models\Agent;
use Illuminate\Support\Str;

class Subdomain
{
    public function __construct(
        private LLMProviderInterface $llmProvider
    ) {}

    public function generateRandomSubDomain(): string
    {
        $response = $this->llmProvider->completion([
            'model' => config('ai.models.title'),
            'max_tokens' => 30,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => prompt('subdomain/generate'),
                ],
            ],

        ]);

        $name = $response['choices'][0]['message']['content'] ?? null;
        $name = str_replace('"', '', $name);

        // Remove any domain-like parts (anything after a dot)
        if ($name) {
            $name = explode('.', $name)[0];
            $name = preg_replace('/[^a-z0-9\-]/', '', $name);
        }

        if (Agent::where('sub_domain', $name)->exists()) {
            return $this->generateRandomSubDomain();
        }

        return Str::of($name)->lower()->trim()->toString();
    }
}
