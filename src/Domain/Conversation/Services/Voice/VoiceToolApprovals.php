<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Voice;

use Illuminate\Support\Str;

/**
 * The actions a voice call holds until the visitor says yes. A call has
 * nothing to click, so a tool that needs a person's approval is not run the
 * first time the model calls it: the model is told to say what it will do
 * and ask. Only the same call (same tool, same arguments) made in the reply
 * to the visitor's very next words runs it, so the server, not the model,
 * makes sure a person spoke in between. Whatever the visitor says after
 * that drops the hold: an old question is never taken as answered.
 *
 * Whether the visitor's words were a yes is {@see SpokenYes}' call, made
 * when the repeat comes in; the words are kept in the audit as the record.
 */
final class VoiceToolApprovals
{
    /**
     * The held calls: the visitor's answer (the provider's id of their
     * speech) by call, null while the question waits for an answer.
     *
     * @var array<string, string|null>
     */
    private array $holds = [];

    /** Hold a call until the visitor answers the question about it. */
    public function hold(string $name, string $arguments): void
    {
        $this->holds[$this->key($name, $arguments)] = null;
    }

    /**
     * The visitor spoke: a waiting question takes this as its answer, and a
     * question answered earlier is dropped.
     */
    public function visitorSpoke(string $itemId): void
    {
        foreach ($this->holds as $key => $answer) {
            if ($answer === null) {
                $this->holds[$key] = $itemId;
            } else {
                unset($this->holds[$key]);
            }
        }
    }

    /** Whether the visitor's speech answers a question about a held call. */
    public function isAnswer(string $itemId): bool
    {
        return collect($this->holds)->containsStrict($itemId);
    }

    /** Whether the call repeats a held one, in the reply to the visitor's answer. */
    public function isConfirmedBy(string $itemId, string $name, string $arguments): bool
    {
        return ($this->holds[$this->key($name, $arguments)] ?? null) === $itemId;
    }

    /** The call ran: a further repeat needs a new yes. */
    public function release(string $name, string $arguments): void
    {
        unset($this->holds[$this->key($name, $arguments)]);
    }

    /** The same call, however the model spaced or ordered its arguments. */
    private function key(string $name, string $arguments): string
    {
        $decoded = json_decode($arguments, true);

        return "{$name}\n".(is_array($decoded)
            ? (string) json_encode(self::sorted($decoded), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : Str::trim($arguments));
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private static function sorted(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(static fn (mixed $item): mixed => is_array($item) ? self::sorted($item) : $item, $value);
    }
}
