<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Translate;

use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Services\StructuredCompletion;
use Domain\Llm\Services\TaskModels;
use Illuminate\Support\Str;

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
    public function __construct(
        private readonly StructuredCompletion $completion,
        private readonly TaskModels $models,
    ) {}

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

        $reply = $this->completion->send(new ChatRequestData(
            model: $this->models->model(LlmTask::Translation),
            messages: [
                ChatMessageData::system(prompt('translate/strings')),
                ChatMessageData::user(json_encode(
                    ['fields' => $fields, 'languages' => $languages],
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                )),
            ],
            tag: AiCallTag::Translation,
            temperature: 0.2,
        ));

        return $this->keepRequested($reply->data ?? [], array_keys($fields), array_keys($languages));
    }

    /**
     * Keep only the requested keys and locales, as non-empty strings.
     *
     * @param  array<array-key, mixed>  $json
     * @param  array<int, string>  $fieldKeys
     * @param  array<int, string>  $localeKeys
     * @return array<string, array<string, string>>
     */
    private function keepRequested(array $json, array $fieldKeys, array $localeKeys): array
    {
        $result = [];

        foreach ($fieldKeys as $field) {
            $byLocale = $json[$field] ?? null;

            if (! is_array($byLocale)) {
                continue;
            }

            foreach ($localeKeys as $locale) {
                $value = $byLocale[$locale] ?? null;

                if (filled($value)) {
                    $result[$field][$locale] = Str::trim($value);
                }
            }
        }

        return $result;
    }
}
