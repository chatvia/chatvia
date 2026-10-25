<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Turn;

use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\MessageOrigin;
use Domain\Conversation\Services\ChatContext;
use Illuminate\Support\Str;

/**
 * The `[n]` markers an agent that cites its sources puts after a sentence,
 * where n is the number of the `<source>` block the sentence came from. Before
 * an answer is stored, its markers are made regular: `[1, 3]` becomes
 * `[1][3]`, and a marker for a block the model was not given (a made-up
 * citation) is removed. Only the web surfaces and the API can show markers;
 * a spoken answer or a reply on a messaging channel loses them all (the model
 * is not asked for them there, so this is a backup). Markers inside code
 * blocks and markdown links are left alone.
 */
class CitationMarkers
{
    /** `[1]` or `[1, 3]`, but not a markdown link `[1](…)`. */
    private const string MARKER = '/ ?\[(\d{1,3}(?:\s*,\s*\d{1,3})*)\](?!\()/';

    /**
     * Makes the answer's markers regular, or strips them from a voice answer.
     * Returns the new text, or null when nothing changed.
     */
    public function clean(ChatContext $context): ?string
    {
        $request = $context->requestData;
        $text = $context->streamedMessage;

        if ($text === '' || ! $request->agent->citesSources()) {
            return null;
        }

        $cleaned = self::shownOn($request) ? $this->normalize($text, $context->sources->count()) : $this->strip($text);

        if ($cleaned === $text) {
            return null;
        }

        $context->resetStreamedMessage();
        $context->appendStreamedMessage($cleaned);

        return $cleaned;
    }

    /**
     * Whether the surface the turn is answered on can show citation chips:
     * the hosted chat, the widget, the command bar, the playground (no
     * source), the API and an eval run (which answers as the web does). Not
     * a voice call or a messaging channel.
     */
    public static function shownOn(StreamRequestData $request): bool
    {
        if ($request->voice) {
            return false;
        }

        return in_array($request->source, [null, MessageOrigin::Playground->value, 'eval', ConversationSource::HostedChat->value, ConversationSource::Widget->value, ConversationSource::Bar->value, ConversationSource::Api->value], true);
    }

    /** Each marker as `[n]`, keeping only blocks 1 to $blocks. */
    public function normalize(string $text, int $blocks): string
    {
        return $this->outsideCode($text, fn (string $part): string => Str::replaceMatches(self::MARKER, function (array $match) use ($blocks): string {
            $numbers = collect(Str::of($match[1])->explode(','))
                ->map(static fn (string $number): int => (int) Str::trim($number))
                ->filter(static fn (int $number): bool => $number >= 1 && $number <= $blocks)
                ->unique()
                ->map(static fn (int $number): string => '['.$number.']')
                ->implode('');

            // The space before a marker belongs to it: "five days [1]." reads "five days[1]."
            return $numbers;
        }, $part) ?? $part);
    }

    /** The text without any marker. */
    public function strip(string $text): string
    {
        return $this->outsideCode($text, static fn (string $part): string => Str::replaceMatches(self::MARKER, '', $part) ?? $part);
    }

    /**
     * Applies $change to the text outside fenced code blocks only.
     *
     * @param  callable(string): string  $change
     */
    private function outsideCode(string $text, callable $change): string
    {
        $parts = Str::of($text)->split('/(```[\s\S]*?```)/', -1, PREG_SPLIT_DELIM_CAPTURE);

        return $parts
            ->map(static fn (string $part): string => Str::startsWith($part, '```') ? $part : $change($part))
            ->implode('');
    }
}
