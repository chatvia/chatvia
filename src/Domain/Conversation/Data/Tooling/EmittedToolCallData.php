<?php

namespace Domain\Conversation\Data\Tooling;

/**
 * One complete tool call the model asked for in a streamed turn: the
 * provider's call id, the tool name it chose, and the raw JSON arguments it
 * wrote. Nothing about it is trusted yet — the tool loop checks the name
 * against the tools it offered before anything runs.
 */
final readonly class EmittedToolCallData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $arguments,
    ) {}

    /**
     * From the transformer's accumulation buffer entry.
     *
     * @param  array{id: string, name: string, arguments: string}  $buffered
     */
    public static function fromStreamBuffer(array $buffered): self
    {
        return new self($buffered['id'], $buffered['name'], $buffered['arguments']);
    }

    /**
     * The call as it appears in the assistant message that requested it
     * (OpenAI shape).
     *
     * @return array{id: string, type: string, function: array{name: string, arguments: string}}
     */
    public function toOpenAiToolCall(): array
    {
        return [
            'id' => $this->id,
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'arguments' => $this->arguments,
            ],
        ];
    }
}
