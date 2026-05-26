<?php

namespace App\Http\Requests\Agents;

use App\Auth\Models\User;
use Domain\Chat\Data\SendHandoffAdminMessageData;
use Domain\Chat\Models\ChatConversation;
use Illuminate\Foundation\Http\FormRequest;

class AgentHandoffMessageRequest extends FormRequest
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
            'content' => ['required', 'string', 'max:10000'],
        ];
    }

    public function toData(ChatConversation $conversation): SendHandoffAdminMessageData
    {
        /** @var User $admin */
        $admin = $this->user();

        return new SendHandoffAdminMessageData(
            conversation: $conversation,
            admin: $admin,
            content: $this->string('content')->toString(),
        );
    }
}
