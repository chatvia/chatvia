<?php

namespace Domain\Widget\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class WidgetHandoffRequestResultData extends Data
{
    public function __construct(
        public string $status,
        public string $conversation_id,
        public string $message,
    ) {}

    public static function make(string $status, string $conversationId): self
    {
        $messages = [
            'human' => 'Already connected to a human agent.',
            'pending' => 'Your request has been sent. Please wait for an agent to connect.',
        ];

        return new self(
            status: $status,
            conversation_id: $conversationId,
            message: $messages[$status] ?? 'Your request is pending. Please wait for an agent.',
        );
    }
}
