<?php

namespace App\Http\Requests\Agent\Handoff;

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Data\Messages\BroadcastChatTypingData;
use Domain\Conversation\Models\Conversation;
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

    public function toData(Conversation $conversation): BroadcastChatTypingData
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
