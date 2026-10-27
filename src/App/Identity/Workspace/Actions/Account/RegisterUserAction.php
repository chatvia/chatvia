<?php

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Actions\MessageLimits\SetMessageLimitAction;
use App\Identity\Workspace\Data\Account\RegisterUserData;
use App\Identity\Workspace\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

/**
 * Create a new account from the sign-up form, together with its own workspace
 * (owner membership, default team and roles), so it is ready to use. The workspace
 * starts with the monthly message limit set in the admin panel (Settings →
 * Workspaces), if one is set.
 *
 * `Registered` (which sends the verification email) is dispatched after the
 * transaction commits, so a listener never sees a user that was rolled back.
 */
class RegisterUserAction
{
    public function __construct(
        private readonly CreateWorkspaceAction $createWorkspace,
        private readonly SetMessageLimitAction $setMessageLimit,
    ) {}

    public function execute(RegisterUserData $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => Hash::make($data->password),
                'registered_ip' => $data->registered_ip,
                'registered_country_code' => $data->registered_country_code,
            ]);

            $workspace = $this->createWorkspace->execute($user);

            $limit = (int) config('chatvia.message_limits.new_workspace');

            if ($limit > 0) {
                $this->setMessageLimit->execute($workspace, $limit);
            }

            return $user;
        });

        Event::dispatch(new Registered($user));

        return $user;
    }
}
