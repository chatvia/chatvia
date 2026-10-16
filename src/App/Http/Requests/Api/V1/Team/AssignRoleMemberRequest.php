<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Team;

use App\Identity\Workspace\Models\User;
use Closure;
use Domain\Membership\Team\Models\Team;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Public-API body for putting a user on a team role. Same gate as the web
 * side (TeamPolicy::updateTeamMember). `user_id` is the user's public id and
 * must name a teammate of the caller's own workspace — any other value is a
 * validation error, never a lookup, so the endpoint cannot confirm another
 * workspace's ids and a malformed id never reaches the uuid column.
 */
class AssignRoleMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team instanceof Team && (bool) $this->user()?->can('updateTeamMember', $team);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // ID of the teammate to put on the role (see `GET /api/v1/users`).
            'user_id' => ['bail', 'required', 'string', 'uuid',
                function (string $attribute, mixed $value, Closure $fail): void {
                    // Membership lives on a pivot, so WorkspaceRule::exists
                    // (a workspace_id column check) cannot scope users.
                    if (! $this->user()->workspace()->teammates()->where('users.external_id', $value)->exists()) {
                        $fail(__('The selected user id is invalid.'));
                    }
                },
            ],
        ];
    }

    /** The teammate the body names (validation already proved they exist). */
    public function member(): User
    {
        /** @var User */
        return $this->user()->workspace()->teammates()
            ->where('users.external_id', $this->string('user_id')->toString())
            ->firstOrFail();
    }
}
