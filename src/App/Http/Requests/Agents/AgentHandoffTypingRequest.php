<?php

namespace App\Http\Requests\Agents;

use App\Auth\Models\User;
use Domain\Chat\Data\BroadcastChatTypingData;
use Domain\Chat\Models\ChatConversation;
use Illuminate\Foundation\Http\FormRequest;

class AgentHandoffTypingRequest extends FormRequest
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
            'is_typing' => ['required', 'boolean'],
        ];
    }

    public function toData(ChatConversation $conversation): BroadcastChatTypingData
    {
        /** @var User $user */
        $user = $this->user();

        return new BroadcastChatTypingData(
            conversationId: $conversation->external_id,
            isTyping: $this->boolean('is_typing'),
            typerType: 'agent',
            typerName: $user->name,
            typerId: (string) $user->id,
        );
    }
}
