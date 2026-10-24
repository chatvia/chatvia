<?php

namespace App\Http\Rules;

use Closure;
use Domain\Llm\Services\ChatFileModel;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a chat file upload when no model that reads files is set up on
 * this install ({@see ChatFileModel}), so the visitor is told at once
 * instead of on their next message.
 */
final readonly class ChatFilesAvailable implements ValidationRule
{
    public function __construct(
        private ChatFileModel $fileModel,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->fileModel->isAvailable()) {
            $fail(__('Chatting with files is not available.'));
        }
    }
}
