<?php

namespace App\Console\Commands;

use App\Identity\Workspace\Models\User;
use Domain\Security\Actions\SetSecurityFeatureOverrideAction;
use Domain\Security\Data\SecurityActorData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Support\Enums\User\UserType;

/**
 * Lets an operator switch the Enterprise Security suite on or off for one
 * workspace regardless of its plan, or hand the decision back to the plan
 * (see {@see SetSecurityFeatureOverrideAction}). The change is written to the
 * workspace's security audit log as a system action.
 */
#[Description('Grant, revoke or reset the Security & Compliance suite for one workspace, regardless of its plan')]
#[Signature('app:security-override {workspace : The workspace owner\'s email or user id} {state : grant, revoke or reset}')]
class SetSecurityOverride extends Command
{
    private const array STATES = ['grant' => true, 'revoke' => false, 'reset' => null];

    public function handle(SetSecurityFeatureOverrideAction $setOverride): int
    {
        $state = strtolower((string) $this->argument('state'));

        if (! array_key_exists($state, self::STATES)) {
            $this->error('The state must be grant, revoke or reset.');

            return self::FAILURE;
        }

        $workspace = $this->workspace((string) $this->argument('workspace'));

        if ($workspace === null) {
            $this->error('No workspace owner matches that email or id.');

            return self::FAILURE;
        }

        $setOverride->execute($workspace, self::STATES[$state], SecurityActorData::system());

        $this->info("Security override for {$workspace->email}: {$state}.");

        return self::SUCCESS;
    }

    private function workspace(string $emailOrId): ?User
    {
        return User::query()
            ->where('type', UserType::Workspace)
            ->where(fn ($query) => ctype_digit($emailOrId)
                ? $query->whereKey((int) $emailOrId)
                : $query->where('email', $emailOrId))
            ->first();
    }
}
