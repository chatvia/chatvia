<?php

namespace App\Http\Requests\Agent\ChatLog;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a corrected answer an operator writes in the chat log for one of
 * the agent's replies; it becomes a Q&A entry future questions are answered
 * from. Access to the agent is checked by route middleware.
 */
class ReviseAnswerRequest extends FormRequest
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
            'message_id' => ['required', 'string'],
            'user_message' => ['required', 'string', 'max:5000'],
            'expected_response' => ['required', 'string', 'max:10000'],
        ];
    }

    public function messageId(): string
    {
        return $this->string('message_id')->toString();
    }

    public function userMessage(): string
    {
        return $this->string('user_message')->toString();
    }

    public function expectedResponse(): string
    {
        return $this->string('expected_response')->toString();
    }
}
