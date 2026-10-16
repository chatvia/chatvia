<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chat;

use App\Http\Rules\WorkspaceRule;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Conversations\CreateApiConversationData;
use Domain\Conversation\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Opening a conversation over the public API: an optional title and an
 * optional chatter of your workspace the conversation is attributed to.
 */
class StoreApiConversationRequest extends FormRequest
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
            // A title for the conversation. Defaults to "New Chat".
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            // ID of one of your chatters to attribute the conversation to. The
            // chatter's monthly message limit then applies to its replies.
            'chatter_id' => ['bail', 'sometimes', 'nullable', 'string', 'uuid', WorkspaceRule::exists('chatters', $this->workspaceUser()->workspace(), 'external_id')],
        ];
    }

    public function toData(Agent $agent): CreateApiConversationData
    {
        $chatterId = $this->string('chatter_id')->toString();

        return new CreateApiConversationData(
            agent: $agent,
            chatter: filled($chatterId)
                ? $this->workspaceUser()->workspace()->chatters()->where('external_id', $chatterId)->firstOrFail()
                : null,
            title: $this->string('title')->toString() ?: Conversation::DEFAULT_TITLE,
        );
    }

    private function workspaceUser(): User
    {
        /** @var User */
        return $this->user();
    }
}
