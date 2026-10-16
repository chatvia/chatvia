<?php

namespace App\Http\Requests\Team;

use App\Http\Rules\WorkspaceRule;
use App\Identity\Workspace\Enums\WorkspaceRole;
use App\Identity\Workspace\Models\User;
use Closure;
use Domain\Membership\Team\Data\InviteTeamMemberData;
use Domain\Membership\Team\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding someone to a team: either a teammate of the same workspace picked by
 * id or an email address to invite. Rejects existing members and
 * duplicate pending invitations here. A picked id only exists for this rule
 * when it is a teammate of the inviter's workspace, so the error never tells
 * whether an id belongs to someone else.
 */
class InviteTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team instanceof Team && $this->user()?->can('addTeamMember', $team);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $team = $this->route('team');
        $validRoles = $team instanceof Team
            ? $team->invitableRoles()->pluck('code')->toArray()
            : [];

        return [
            'user_id' => [
                'nullable',
                'integer',
                WorkspaceRule::exists('workspace_user', $this->user()->workspace(), 'user_id')
                    ->where('role', WorkspaceRole::Member->value),
                $this->userIsInvitable($team),
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                'required_without:user_id',
                $this->emailIsInvitable($team),
            ],
            'role' => ['required', 'string', Rule::in($validRoles)],
        ];
    }

    /**
     * Gate a teammate picked by id (the `exists` rule already limits it to
     * this workspace's employees): reject anyone who already belongs to the
     * team, since the `team_user` pivot has a composite primary key.
     */
    private function userIsInvitable(mixed $team): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($team): void {
            if (! $team instanceof Team || $value === null) {
                return;
            }

            $user = $this->user()?->workspace()->teammates()->whereKey($value)->first();

            if (! $user) {
                return;
            }

            if ($team->hasUser($user)) {
                $fail(__('This user is already a member of the team.'));
            }
        };
    }

    /**
     * Gate an email invite. Reject an email that already belongs to a member
     * or that already has a pending invitation — `team_invitations` is unique
     * on `[team_id, email]`. Accounts outside this workspace are not checked
     * here, so the answer never tells the inviter about them; accepting
     * refuses them and tells only the invitee.
     */
    private function emailIsInvitable(mixed $team): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($team): void {
            if (! $team instanceof Team || blank($value)) {
                return;
            }

            if ($team->hasUserWithEmail($value)) {
                $fail(__('This user is already a member of the team.'));

                return;
            }

            if ($team->invitations()->where('email', strtolower($value))->exists()) {
                $fail(__('An invitation has already been sent to this email.'));
            }
        };
    }

    public function toData(Team $team): InviteTeamMemberData
    {
        /** @var User $user */
        $user = $this->user();

        return new InviteTeamMemberData(
            team: $team,
            inviter: $user,
            role: $this->string('role')->toString(),
            userId: $this->input('user_id') !== null ? (int) $this->input('user_id') : null,
            email: $this->input('email'),
        );
    }
}
