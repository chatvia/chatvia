<?php

namespace App\Support;

class Subdomain
{
    public function generateRandomSubDomain()
    {
        $response = OpenRouter::completion([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => '
Generate a unique, clever, and meaningful name for a chat app.
- Combine 1–2 real, relevant English words.
- Make it subdomain-safe: use only lowercase letters, numbers, and dashes.
- Dashes are optional but can be used for clarity.
- Be creative and witty—think metaphor, function, or vibe.
- Return only the name. No domain, no punctuation, no explanation.
	',
                ],
            ],
            'max_tokens' => '20',
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

        return $name;
    }
}
