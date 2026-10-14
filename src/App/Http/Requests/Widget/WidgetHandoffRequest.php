<?php

namespace App\Http\Requests\Widget;

use Domain\Widget\Data\Conversation\WidgetConversationRefData;
use Domain\Widget\WidgetHeaders;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A widget visitor asks for a human operator on one of their conversations.
 */
class WidgetHandoffRequest extends FormRequest
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
        ];
    }

    public function toData(): WidgetConversationRefData
    {
        $sessionId = $this->headers->get(WidgetHeaders::SESSION_ID);

        return new WidgetConversationRefData(
            conversationId: $this->string('conversation_id')->toString(),
            sessionId: filled($sessionId) ? $sessionId : null,
        );
    }
}
