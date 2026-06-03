<?php

namespace App\Http\Requests\Widget;

use Domain\Conversation\Data\Messages\BroadcastChatTypingData;
use Domain\Conversation\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;

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
            typerId: $this->header('X-SESSION-ID'),
        );
    }
}
