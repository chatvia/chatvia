<?php

namespace App\Http\Requests\Chat;

use Domain\Conversation\Enums\MessageFeedback;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a thumbs-up / thumbs-down (or a cleared rating) on an assistant
 * reply in the hosted chat. The conversation is looked up within the
 * visitor's own session.
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
}
