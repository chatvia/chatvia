<?php

namespace App\Http\Requests\Team;

use App\Identity\Workspace\Enums\UserType;
use App\Identity\Workspace\Models\User;
use Closure;
use Domain\Membership\Team\Data\InviteTeamMemberData;
use Domain\Membership\Team\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding someone to a team: either a managed user picked by id or an email
 * address to invite. Rejects chatters, existing members and duplicate pending
 * invitations here, so the Action only enforces the owner-side quota rules.
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
                'exists:users,id',
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
     * Gate an existing user picked by id. Teams are made of Employees (and the
     * Workspace owner) — a Chatter only ever logs into `/chat` and must never
     * join a team. Also reject anyone who already belongs to the team, since
     * the `team_user` pivot has a composite primary key.
     */
    private function userIsInvitable(mixed $team): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($team): void {
            if (! $team instanceof Team || $value === null) {
                return;
            }

            $user = User::find($value);

            if (! $user) {
                return;
            }

            if ($user->isChatter()) {
                $fail('This account is a chat user and cannot be added to a team.');

                return;
            }

            if ($team->hasUser($user)) {
                $fail('This user is already a member of the team.');
            }
        };
    }

    /**
     * Gate an email invite. Reject an email that already belongs to a member,
     * to a Chatter (chat users cannot join a team), or that already has a
     * pending invitation — `team_invitations` is unique on `[team_id, email]`.
     */
    private function emailIsInvitable(mixed $team): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($team): void {
            if (! $team instanceof Team || blank($value)) {
                return;
            }

            if ($team->hasUserWithEmail($value)) {
                $fail('This user is already a member of the team.');

                return;
            }

            if (User::where('email', $value)->where('type', UserType::Chatter)->exists()) {
                $fail('This email belongs to a chat user and cannot be added to a team.');

                return;
            }

            if ($team->invitations()->where('email', $value)->exists()) {
                $fail('An invitation has already been sent to this email.');
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
