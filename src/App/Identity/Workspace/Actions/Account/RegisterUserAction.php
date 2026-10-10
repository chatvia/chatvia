<?php

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Actions\MessageLimits\SetMessageLimitAction;
use App\Identity\Workspace\Data\Account\RegisterUserData;
use App\Identity\Workspace\Enums\UserType;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Create a new workspace owner from the sign-up form, together with the
 * default team and its roles, so the account is ready to use. The workspace
 * starts with the monthly message limit set in the admin panel (Settings →
 * Workspaces), if one is set.
 *
 * `Registered` (which sends the verification email) is dispatched after the
 * transaction commits, so a listener never sees a user that was rolled back.
 */
class RegisterUserAction
{
    public function __construct(
        private readonly TeamService $teams,
        private readonly SetMessageLimitAction $setMessageLimit,
    ) {}

    public function execute(RegisterUserData $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => Hash::make($data->password),
                'domain' => $data->domain,
                'registered_ip' => $data->registered_ip,
                'registered_country_code' => $data->registered_country_code,
                'type' => UserType::Workspace,
            ]);

            $this->teams->createDefaultTeam($user);

            $user->bootstrapCurrentTeam();

            $limit = (int) config('chatvia.message_limits.new_workspace');

            if ($limit > 0) {
                $this->setMessageLimit->execute($user, $limit);
            }

            return $user;
        });

        event(new Registered($user));

        return $user;
    }
}
