<?php

namespace App\Http\Requests\Settings\Member;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Data\InviteWorkspaceMemberData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

/**
 * Validates a workspace-level member invite: an email plus at least one agent
 * to grant access to. Agents are resolved against the caller's workspace and
 * limited to **protected** agents — public agents have no per-member access, so
 * they can't be assigned.
 */
class InviteWorkspaceMemberRequest extends FormRequest
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
            'agents.required' => 'Select at least one agent to give this member access to.',
            'agents.min' => 'Select at least one agent to give this member access to.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $requested = collect($this->validated('agents'))->filter()->unique();

            // Every requested id must resolve to a protected agent in the
            // workspace, otherwise the selection is stale or out of bounds.
            if ($this->resolveAgents()->count() !== $requested->count()) {
                $validator->errors()->add(
                    'agents',
                    'One or more selected agents are not available. Only protected agents in your workspace can be assigned.',
                );
            }
        });
    }

    public function email(): string
    {
        return strtolower(trim((string) $this->validated('email')));
    }

    public function toData(): InviteWorkspaceMemberData
    {
        return new InviteWorkspaceMemberData(
            email: $this->email(),
            agents: $this->resolveAgents(),
        );
    }

    /** @return Collection<int, Agent> */
    private function resolveAgents(): Collection
    {
        if ($this->resolvedAgents !== null) {
            return $this->resolvedAgents;
        }

        /** @var User $user */
        $user = $this->user();

        $ids = collect($this->input('agents', []))->filter()->unique()->all();

        return $this->resolvedAgents = $user->workspace()
            ->agents()
            ->where('is_public', false)
            ->whereIn('external_id', $ids)
            ->get();
    }
}
