<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chatter;

use App\Http\Rules\WorkspaceRule;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentSignInTokenService;
use Closure;
use Domain\Agent\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The agent and the person a sign-in link is for. Both must be in your
 * workspace: any other id is a validation error (422), worded the same for
 * "unknown" and "someone else's", so the answer never shows whether an id
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
            'agent_id' => ['bail', 'required', 'string', WorkspaceRule::exists('agents', $this->workspaceUser()->workspace(), 'external_id')->withoutTrashed()],
            // ID of the user the link signs in: one of your chatters, or the
            // owner or a teammate of your workspace (see `GET /api/v1/users`).
            'user_id' => ['bail', 'required', 'string', 'uuid',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $this->signeeQueryMatches($value)) {
                        $fail(__('The selected user id is invalid.'));
                    }
                },
            ],
            // How long the link stays valid, in seconds (60–86400). Defaults to one hour.
            'ttl_seconds' => ['sometimes', 'integer', 'min:60', 'max:'.AgentSignInTokenService::MAX_TTL_SECONDS],
        ];
    }

    /** The workspace agent the body names (validation already proved it). */
    public function agent(): Agent
    {
        /** @var Agent */
        return $this->workspaceUser()->workspace()->agents()
            ->where('external_id', $this->string('agent_id')->toString())
            ->firstOrFail();
    }

    /** The chatter or staff member the body names (validation already proved them). */
    public function signee(): Chatter|User
    {
        $id = $this->string('user_id')->toString();
        $workspace = $this->workspaceUser()->workspace();

        return $workspace->chatters()->where('external_id', $id)->first()
            ?? $workspace->members()->where('users.external_id', $id)->firstOrFail();
    }

    private function signeeQueryMatches(mixed $id): bool
    {
        $workspace = $this->workspaceUser()->workspace();

        return $workspace->chatters()->where('external_id', $id)->exists()
            || $workspace->members()->where('users.external_id', $id)->exists();
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
