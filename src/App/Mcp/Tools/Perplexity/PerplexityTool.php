<?php

namespace App\Mcp\Tools\Perplexity;

use Domain\Integration\Builtin\Data\Perplexity\PerplexityAnswerData;
use Domain\Integration\Builtin\Data\Perplexity\PerplexityCitationData;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * Shared result shape of every Perplexity tool: the answer followed by a
 * numbered "Sources" list as text, and the answer plus its citations as
 * structured content.
 */
abstract class PerplexityTool extends Tool
{
    protected const string MISSING_KEY = 'No Perplexity API key was provided. Reconnect the Perplexity connector with a valid key.';

    /**
     * @param  array<string, mixed>  $extra  Tool-specific structured fields appended after the citations.
     */
    protected function answerResponse(PerplexityAnswerData $answer, array $extra = []): ResponseFactory
    {
        $citations = array_map(static fn (PerplexityCitationData $citation): array => [
            'title' => $citation->title,
            'url' => $citation->url,
        ], $answer->citations);

        return Response::make(Response::text($this->summary($answer)))->withStructuredContent([
            'answer' => $answer->answer,
            'citations' => $citations,
            ...$extra,
        ]);
    }

    private function summary(PerplexityAnswerData $answer): string
    {
        if ($answer->citations === []) {
            return $answer->answer;
        }

        $summary = $answer->answer."\n\nSources:\n";
        foreach ($answer->citations as $i => $citation) {
            $summary .= sprintf("[%d] %s — %s\n", $i + 1, $citation->title ?: $citation->url, $citation->url);
        }

        return $summary;
    }
}
