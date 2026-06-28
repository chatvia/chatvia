<?php

namespace Domain\Llm\Operations\Starters;

use Domain\Llm\Contracts\LlmProviderInterface;
use Illuminate\Support\Collection;

/**
 * Suggests conversation starters from a sample of an agent's knowledge-base
 * content. One LLM call returns up to N short questions a visitor might ask,
 * grounded in the supplied content. Returns plain strings — the Setup UI lets
 * the owner pick which to add (icons stay the owner's choice).
 */
class StarterGenerator
{
    public function __construct(public LlmProviderInterface $provider) {}

    /**
     * @param  array<int, string>  $content  knowledge-base content samples
     * @return array<int, string> suggested starter questions (≤ $count)
     */
    public function generate(array $content, int $count): array
    {
        $count = max(1, min(10, $count));

        if ($content === []) {
            return [];
        }

        $body = Collection::make($content)->take(40)->implode("\n---\n");

        $raw = $this->provider->completion([
            'model' => config('chatvia.ai.models.starters'),
            'temperature' => 0.7,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => prompt('starters/generate', ['count' => $count]),
                ],
                [
                    'role' => 'user',
                    'content' => "Knowledge base content:\n<content>\n".$body."\n</content>",
                ],
            ],
        ])->throw()->json('choices.0.message.content');

        return $this->parse(is_string($raw) ? $raw : '', $count);
    }

    /**
     * Decode the model's JSON array of strings, tolerating fences/prose.
     *
     * @return array<int, string>
     */
    private function parse(string $content, int $count): array
    {
        $content = trim($content);

        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $content) ?? $content;
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            $start = strpos($content, '[');
            $end = strrpos($content, ']');

            if ($start === false || $end === false || $end <= $start) {
                return [];
            }

            $decoded = json_decode(substr($content, $start, $end - $start + 1), true);
        }

        if (! is_array($decoded)) {
            return [];
        }

        return Collection::make($decoded)
            ->filter(fn ($value): bool => is_string($value))
            ->map(fn (string $value): string => trim($value))
            ->filter(fn (string $value): bool => $value !== '' && mb_strlen($value) <= 255)
            ->unique()
            ->take($count)
            ->values()
            ->all();
    }
}
