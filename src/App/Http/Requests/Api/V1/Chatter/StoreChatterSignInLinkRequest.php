<?php

namespace App\Http\Requests\Api\V1\Chatter;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentSignInTokenService;
use Domain\Agent\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * The agent and the person a sign-in link is for. Both must be in your
 * workspace: any other id is a 404, so the answer never shows whether the id
 * exists somewhere else.
 */
class StoreChatterSignInLinkRequest extends FormRequest
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
            // ID of the agent to sign the user into.
            'agent_id' => ['required', 'string'],
            // ID of the user the link signs in: one of your chatters, or the
            // owner or a teammate of your workspace.
            'user_id' => ['required', 'string'],
            // How long the link stays valid, in seconds (60–3600). Defaults to one hour.
            'ttl_seconds' => ['sometimes', 'integer', 'min:60', 'max:3600'],
        ];
    }

    /**
     * The workspace agent the body names; 404 when it is not one.
     */
    public function agent(): Agent
    {
        /** @var Agent */
        return $this->workspaceUser()->workspace()->agents()
            ->where('external_id', $this->string('agent_id')->toString())
            ->firstOrFail();
    }

    /**
     * The chatter or staff member of the workspace the body names; 404 when it
     * is neither.
     */
    public function signee(): Chatter|User
    {
        $id = $this->string('user_id')->toString();

        // The ids are uuid columns: anything else can only be a 404.
        abort_unless(Str::isUuid($id), 404);

        $workspace = $this->workspaceUser()->workspace();

        /** @var Chatter|User */
        return $workspace->chatters()->where('external_id', $id)->first()
            ?? $workspace->members()->where('users.external_id', $id)->firstOrFail();
    }

    public function ttlSeconds(): int
    {
        return $this->integer('ttl_seconds', AgentSignInTokenService::DEFAULT_TTL_SECONDS);
    }

    private function workspaceUser(): User
    {
        /** @var User */
        return $this->user();
    }
}
