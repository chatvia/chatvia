<?php

namespace App\Services\Infrastructure;

use App\Contracts\LLMProviderInterface;
use App\Models\Language;

class LanguageDetection
{
    public function __construct(
        public LLMProviderInterface $provider
    )
    {
    }

    public function detect(string $text): ?Language
    {
        $languageCode = $this->provider->completion([
            'model' => 'llama-3.1-8b-instruct',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a language detection expert. Analyze the given text and respond with ONLY the ISO 639-1 two-letter language code (e.g., "en" for English, "es" for Spanish, "fr" for French, "de" for German, "ar" for Arabic, "zh" for Chinese, etc.). Respond with ONLY the two-letter code, nothing else.',
                ],
                [
                    'role' => 'user',
                    'content' => "Detect the language of the following text:\n\n\"{$text}\"",
                ],
            ],
        ]);

        $languageCode = strtolower(trim($languageCode));
        $languageCode = preg_replace('/[^a-z]/', '', $languageCode);

        if (strlen($languageCode) !== 2) {
            return null;
        }

        return Language::where('code', $languageCode)->first();
    }
}
