<?php

namespace Domain\Conversation\Rules;

use Closure;
use Domain\Llm\Services\DocumentLlm;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a chat PDF upload when no LLM that can read PDF documents is set up
 * on this install, so the visitor is told at once instead of on their next
 * message.
 */
final readonly class DocumentChatAvailable implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! app(DocumentLlm::class)->isAvailable()) {
            $fail('Chatting with PDF files is not available.');
        }
    }
}
