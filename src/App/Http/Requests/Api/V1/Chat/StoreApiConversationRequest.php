<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chat;

use App\Http\Requests\Concerns\ScopesRulesToWorkspace;
use App\Http\Rules\WorkspaceRule;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Conversations\CreateApiConversationData;
use Domain\Conversation\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Opening a conversation over the public API: an optional title and an
 * optional chatter of your workspace the conversation is attributed to. The
 * chatter must have access to the agent, the same rule the hosted chat and the
 * widget apply, so the API never opens a protected agent to a chatter who was
 * never granted it.
 */
class StoreApiConversationRequest extends FormRequest
{
    use ScopesRulesToWorkspace;

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
            'chatter_id' => ['bail', 'sometimes', 'nullable', 'string', 'uuid', WorkspaceRule::exists('chatters', $this->ruleWorkspaceId(), 'external_id')],
        ];
    }

    /**
     * A chatter without access to the agent reads like an unknown chatter.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $chatter = $this->chatter();
                $agent = $this->route('agent');

                if ($chatter instanceof Chatter && $agent instanceof Agent && ! $chatter->canAccessAgent($agent)) {
                    $validator->errors()->add('chatter_id', __('validation.exists', ['attribute' => 'chatter id']));
                }
            },
        ];
    }

    public function toData(Agent $agent): CreateApiConversationData
    {
        return new CreateApiConversationData(
            agent: $agent,
            chatter: filled($this->string('chatter_id')->toString())
                ? $this->chatter() ?? abort(404)
                : null,
            title: $this->string('title')->toString() ?: Conversation::DEFAULT_TITLE,
        );
    }

    /** The workspace chatter named by `chatter_id`, if any. */
    private function chatter(): ?Chatter
    {
        $chatterId = $this->string('chatter_id')->toString();

        return blank($chatterId)
            ? null
            : $this->workspaceUser()->workspace()->chatters()->where('external_id', $chatterId)->first();
    }

    private function workspaceUser(): User
    {
        /** @var User */
        return $this->user();
    }
}
