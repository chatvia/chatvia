<?php

namespace Domain\Llm\Operations\Translate;

use Domain\Llm\Contracts\LlmProviderInterface;

/**
 * One structured-JSON LLM call that localizes a set of short UI strings into
 * many languages at once. Generic over the caller: appearance text (chat title /
 * welcome / placeholder) and conversation starters both hand it a
 * `key => base text` map and get back `key => (locale => translation)`. Callers
 * keep the base strings as the fallback, so a partial/empty model response
 * degrades gracefully rather than blanking anything.
 */
class TextTranslator
{
    public function __construct(public LlmProviderInterface $provider) {}

    /**
     * @param  array<string, string>  $fields  caller key => base (source) text
     * @param  array<string, string>  $languages  locale code => language name
     * @return array<string, array<string, string>> key => (locale => translation)
     */
    public function translate(array $fields, array $languages): array
    {
        if ($fields === [] || $languages === []) {
            return [];
        }

        $content = $this->provider->completion([
            'model' => config('chatvia.ai.models.translator'),
            'temperature' => 0.2,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => prompt('translate/strings'),
                ],
                [
                    'role' => 'user',
                    'content' => json_encode(
                        ['fields' => $fields, 'languages' => $languages],
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                    ),
                ],
            ],
        ])->throw()->json('choices.0.message.content');

        return $this->parse(is_string($content) ? $content : '', array_keys($fields), array_keys($languages));
    }

    /**
     * Decode the model's JSON and keep only the requested keys/locales as
     * non-empty strings. Tolerates code fences and surrounding prose.
     *
     * @param  array<int, string>  $fieldKeys
     * @param  array<int, string>  $localeKeys
     * @return array<string, array<string, string>>
     */
    private function parse(string $content, array $fieldKeys, array $localeKeys): array
    {
        $json = $this->extractJson($content);

        if (! is_array($json)) {
            return [];
        }

        $result = [];

        foreach ($fieldKeys as $field) {
            $byLocale = $json[$field] ?? null;

            if (! is_array($byLocale)) {
                continue;
            }

            foreach ($localeKeys as $locale) {
                $value = $byLocale[$locale] ?? null;

                if (is_string($value) && trim($value) !== '') {
                    $result[$field][$locale] = trim($value);
                }
            }
        }

        return $result;
    }

    private function extractJson(string $content): mixed
    {
        $content = trim($content);

        // Strip a ```json … ``` fence if the model wrapped its answer.
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $content) ?? $content;
        }

        $decoded = json_decode($content, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        // Fall back to the outermost { … } block when prose leaked in.
        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return json_decode(substr($content, $start, $end - $start + 1), true);
    }
}
