<?php

namespace App\Console\Commands;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Support\Enums\User\UserType;

/**
 * Creates an admin account: access to the admin panel plus a normal
 * workspace with its default team, so the account can build agents straight
 * away. On a self-hosted install this is the usual way to create the first
 * account.
 */
#[Description('Create an admin user')]
#[Signature('admin:create-user {email} {name} {password?}')]
class CreateAdminUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TeamService $teams): int
    {
        $email = $this->argument('email');
        $name = $this->argument('name');
        $password = $this->argument('password') ?? $this->secret('Enter password for the admin user');

        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            $this->error('User with this email already exists!');

            return 1;
        }

        // Create the admin user and the team it works in.
        $user = DB::transaction(function () use ($name, $email, $password, $teams): User {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'type' => UserType::Admin,
            ]);

            $teams->createDefaultTeam($user);
            $user->bootstrapCurrentTeam();

            return $user;
        });

        $this->info('Admin user created successfully!');
        $this->info("Email: {$user->email}");
        $this->info("Name: {$user->name}");

        return 0;
    }
}
