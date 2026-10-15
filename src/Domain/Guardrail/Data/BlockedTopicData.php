<?php

declare(strict_types=1);

namespace Domain\Guardrail\Data;

use Domain\Agent\ValueObjects\TranslatableText;
use Domain\Guardrail\Enums\TopicResponse;
use Spatie\LaravelData\Data;

/**
 * One topic the agent must not discuss. The description and a few example
 * questions tell the classifier what the topic covers (a bare name such as
 * "Medical" is easy to misread); the action says what the visitor gets
 * instead of an answer. `reply_translations` (locale → text) are the AI
 * translations of `reply`, written by SaveGuardrailSettingsAction.
 */
class BlockedTopicData extends Data
{
    /**
     * @param  list<string>  $examples
     * @param  array<string, string>  $reply_translations
     */
    public function __construct(
        public string $name,
        public ?string $description = null,
        public array $examples = [],
        public TopicResponse $action = TopicResponse::Refuse,
        public ?string $reply = null,
        public array $reply_translations = [],
    ) {}

    /** Read a stored entry; a plain string is a topic with only a name. */
    public static function fromStored(mixed $stored): ?self
    {
        if (is_string($stored)) {
            return filled(trim($stored)) ? new self(trim($stored)) : null;
        }

        if (! is_array($stored) || blank($stored['name'] ?? null)) {
            return null;
        }

        return new self(
            name: trim((string) $stored['name']),
            description: filled($stored['description'] ?? null) ? trim((string) $stored['description']) : null,
            examples: array_values(array_filter(
                array_map(static fn (mixed $example): string => is_string($example) ? trim($example) : '', (array) ($stored['examples'] ?? [])),
                filled(...),
            )),
            action: TopicResponse::tryFrom((string) ($stored['action'] ?? '')) ?? TopicResponse::Refuse,
            reply: filled($stored['reply'] ?? null) ? trim((string) $stored['reply']) : null,
            reply_translations: array_filter((array) ($stored['reply_translations'] ?? []), is_string(...)),
        );
    }

    /** The topic's own reply in the visitor's language, or null when it has none. */
    public function replyIn(?string $language): ?string
    {
        return $this->reply === null ? null : new TranslatableText($this->reply, $this->reply_translations)->in($language);
    }

    /**
     * The line the classifier reads: name, description and examples.
     */
    public function forClassifier(): string
    {
        $line = $this->name;

        if ($this->description !== null) {
            $line .= ' — '.$this->description;
        }

        if ($this->examples !== []) {
            $line .= ' (for example: '.implode('; ', array_map(static fn (string $example): string => '"'.$example.'"', $this->examples)).')';
        }

        return $line;
    }

    /**
     * @return array<string, mixed>
     */
    public function toStored(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'examples' => $this->examples,
            'action' => $this->action->value,
            'reply' => $this->reply,
            'reply_translations' => $this->reply_translations,
        ];
    }
}
