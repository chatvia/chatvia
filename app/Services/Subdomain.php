<?php

namespace App\Services;

use App\Models\Agent;
use App\Services\Infrastructure\LLM\OpenRouter;
use App\Services\Prompts\PromptLoader;
use Illuminate\Support\Str;

class Subdomain
{
    public function generateRandomSubDomain(): string
    {
        $response = OpenRouter::completion([
            'model' => 'gpt-4.1-mini',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => PromptLoader::load('subdomain/generate'),
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
