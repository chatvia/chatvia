<?php

namespace App\Http\Requests\Widget;

use Domain\Conversation\Data\Messages\BroadcastChatTypingData;
use Domain\Conversation\Models\Conversation;
use Domain\Widget\WidgetHeaders;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The visitor started or stopped typing in a handed-off conversation; the
 * operator sees the indicator. The typer is identified by the session id.
 */
class WidgetTypingRequest extends FormRequest
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
            'is_typing' => ['required', 'boolean'],
        ];
    }

    public function toData(Conversation $conversation): BroadcastChatTypingData
    {
        return new BroadcastChatTypingData(
            conversationId: $this->string('conversation_id')->toString(),
            isTyping: $this->boolean('is_typing'),
            typerType: 'user',
            typerName: $conversation->displayName() ?? 'Visitor',
            typerId: $this->header(WidgetHeaders::SESSION_ID),
        );
    }
}
