<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates which message the hosted-chat visitor removes from a conversation
 * (it goes together with the message before it). The conversation is looked
 * up within the visitor's own session.
 */
class DeleteMessageRequest extends FormRequest
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
}
