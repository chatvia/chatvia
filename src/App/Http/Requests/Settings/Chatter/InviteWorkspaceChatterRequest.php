<?php

namespace App\Http\Requests\Settings\Chatter;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Data\InviteWorkspaceChatterData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * Validates a workspace-level member invite: an email plus at least one agent
 * to grant access to. Agents are resolved against the caller's workspace and
 * limited to **protected** agents — public agents have no per-member access, so
 * they can't be assigned.
 */
class InviteWorkspaceChatterRequest extends FormRequest
{
    /** @var Collection<int, Agent>|null */
    private ?Collection $resolvedAgents = null;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'agents' => ['required', 'array', 'min:1'],
            'agents.*' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'agents.required' => __('Select at least one agent to give this member access to.'),
            'agents.min' => __('Select at least one agent to give this member access to.'),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $requested = $this->safe()->collect('agents')->filter()->unique();

                // Every requested id must resolve to a protected agent in the
                // workspace, otherwise the selection is stale or out of bounds.
                if ($this->resolveAgents()->count() !== $requested->count()) {
                    $validator->errors()->add(
                        'agents',
                        __('One or more selected agents are not available. Only protected agents in your workspace can be assigned.'),
                    );
                }
            },
        ];
    }

    public function email(): string
    {
        return Str::lower(Str::trim((string) $this->validated('email')));
    }

    public function toData(): InviteWorkspaceChatterData
    {
        return new InviteWorkspaceChatterData(
            email: $this->email(),
            agents: $this->resolveAgents(),
        );
    }

    /** @return Collection<int, Agent> */
    private function resolveAgents(): Collection
    {
        if ($this->resolvedAgents instanceof Collection) {
            return $this->resolvedAgents;
        }

        /** @var User $user */
        $user = $this->user();

        $ids = $this->collect('agents')->filter()->unique()->all();

        return $this->resolvedAgents = $user->workspace()
            ->agents()
            ->where('is_public', false)
            ->whereIn('external_id', $ids)
            ->get();
    }
}
