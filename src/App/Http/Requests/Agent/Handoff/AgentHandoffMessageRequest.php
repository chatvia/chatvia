<?php

namespace App\Http\Requests\Agent\Handoff;

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Data\Handoff\SendHandoffAdminMessageData;
use Domain\Conversation\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an operator's reply into a handed-off conversation.
 */
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

    public function toData(Conversation $conversation): SendHandoffAdminMessageData
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
