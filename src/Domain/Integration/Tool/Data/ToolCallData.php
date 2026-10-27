<?php

namespace Domain\Integration\Tool\Data;

use Domain\Integration\Tool\Dispatch\ToolCallerIdentity;
use Domain\Integration\Tool\ValueObjects\ToolName;

/**
 * One tool call the LLM asked for, as handed to a dispatcher: which tool, the
 * raw JSON arguments the model produced, and who is talking.
 *
 * The two external ids are stamped on the outbound request (see
 * {@see ToolCallerIdentity}) so a receiver can scope its writes to the
 * conversation, and know which signed-in chatter is talking. Both are opaque
 * public UUIDs and null when unknown.
 */
final readonly class ToolCallData
{
    public function __construct(
        public ToolName $name,
        public string $arguments,
        public ?string $conversationExternalId = null,
        public ?string $chatterExternalId = null,
    ) {}

    /**
     * Build a call from the name string the LLM emitted.
     */
    public static function named(string $name, string $arguments, ?string $conversationExternalId = null, ?string $chatterExternalId = null): self
    {
        return new self(ToolName::parse($name), $arguments, $conversationExternalId, $chatterExternalId);
    }
}
