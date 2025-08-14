<?php

namespace App\Support;

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
                    'content' => '

Generate a clever, witty, or playful name for an AI agent chat app by combining 2–3 relevant English words.

The name must be subdomain-safe, using only lowercase letters, numbers, and optional dashes (for clarity).

Return only the name—no domains, punctuation, or explanations.

You must not use any brand names, trademarks, etc...
	',
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

        if (\App\Models\Agent::where('sub_domain', $name)->exists()) {
            return $this->generateRandomSubDomain();
        }

        return Str::of($name)->lower()->trim()->toString();
    }
}
