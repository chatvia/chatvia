<?php

namespace App\Http\Requests\Chat;

use Domain\Conversation\Actions\Activity\RecordMessageFeedbackAction;
use Domain\Conversation\Enums\MessageFeedback;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a thumbs-up / thumbs-down (or a cleared rating) on an assistant
 * reply in the hosted chat, with an optional note on a thumbs-down. The
 * conversation is looked up within the visitor's own session.
 */
class MessageFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'conversation_id' => ['required', 'string'],
            'message_id' => ['required', 'string'],
            'feedback' => ['nullable', Rule::enum(MessageFeedback::class)],
            'comment' => ['nullable', 'string', 'max:'.RecordMessageFeedbackAction::COMMENT_MAX_LENGTH],
        ];
    }

    public function conversationId(): string
    {
        return $this->string('conversation_id')->toString();
    }

    public function messageId(): string
    {
        return $this->string('message_id')->toString();
    }

    public function feedback(): ?MessageFeedback
    {
        return $this->enum('feedback', MessageFeedback::class);
    }

    public function comment(): ?string
    {
        return $this->filled('comment') ? $this->string('comment')->toString() : null;
    }
}
