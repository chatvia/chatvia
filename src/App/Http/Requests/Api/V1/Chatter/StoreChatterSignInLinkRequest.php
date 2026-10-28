<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chatter;

use App\Http\Requests\Concerns\ValidatesManageableAgents;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentSignInTokens;
use Closure;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The agent and the person a sign-in link is for. Both must be in your
 * workspace: any other id is a validation error (422), worded the same for
 * "unknown" and "someone else's", so the answer never shows whether an id
 * exists somewhere else. A teammate's key names only the agents their team
 * role reaches, as on the dashboard. The person must be able to reach the
 * agent, or the link would never sign them in, and only the owner may sign in a staff
 * account other than their own, so a teammate's key cannot open a chat as
 * the owner or another teammate.
 */
class StoreChatterSignInLinkRequest extends FormRequest
{
    use ValidatesManageableAgents;

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
            // ID of the agent to sign the user into: one of your agents that
            // your key may manage (a teammate's key reaches the agents of
            // their team role only).
            'agent_id' => ['bail', 'required', 'string', $this->manageableAgentExists()],
            // ID of the user the link signs in: one of your chatters with
            // access to the agent, or (owner keys only) the owner or a
            // teammate of your workspace (see `GET /api/v1/users`).
            'user_id' => ['bail', 'required', 'string', 'uuid',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $signee = $this->findSignee($value);

                    if ($signee === null || ! $this->maySign($signee)) {
                        $fail(__('The selected user id is invalid.'));

                        return;
                    }

                    $agent = $this->findAgent();

                    if ($agent instanceof Agent && ! $signee->canAccessAgent($agent)) {
                        $fail(__('This user has no access to the agent.'));
                    }
                },
            ],
            // How long the link stays valid, in seconds (60–86400). Defaults to one hour.
            'ttl_seconds' => ['sometimes', 'integer', 'min:60', 'max:'.AgentSignInTokens::MAX_TTL_SECONDS],
        ];
    }

    /** The workspace agent the body names (validation already proved it). */
    public function agent(): Agent
    {
        return $this->findAgent() ?? throw (new ModelNotFoundException)->setModel(Agent::class);
    }

    /** The chatter or staff member the body names (validation already proved them). */
    public function signee(): Chatter|User
    {
        return $this->findSignee($this->string('user_id')->toString())
            ?? throw (new ModelNotFoundException)->setModel(User::class);
    }

    private function findAgent(): ?Agent
    {
        /** @var ?Agent */
        return $this->workspaceUser()->workspace()->agents()
            ->where('external_id', $this->string('agent_id')->toString())
            ->first();
    }

    private function findSignee(mixed $id): Chatter|User|null
    {
        $workspace = $this->workspaceUser()->workspace();

        return $workspace->chatters()->where('external_id', $id)->first()
            ?? $workspace->members()->where('users.external_id', $id)->first();
    }

    /** A teammate's key signs in chatters and the teammate themself only. */
    private function maySign(Chatter|User $signee): bool
    {
        $caller = $this->workspaceUser();

        return $signee instanceof Chatter || $caller->isWorkspaceOwner() || $signee->is($caller);
    }

    public function ttlSeconds(): int
    {
        return $this->integer('ttl_seconds', AgentSignInTokens::DEFAULT_TTL_SECONDS);
    }

    private function workspaceUser(): User
    {
        /** @var User */
        return $this->user();
    }
}
