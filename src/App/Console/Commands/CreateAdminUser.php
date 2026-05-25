<?php

namespace App\Console\Commands;

use App\Auth\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

#[Description('Create an admin user')]
#[Signature('admin:create-user {email} {name} {password?}')]
class CreateAdminUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $name = $this->argument('name');
        $password = $this->argument('password') ?? $this->secret('Enter password for the admin user');

        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            $this->error('User with this email already exists!');

            return 1;
        }

        // Create the admin user
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'type' => 'admin',
        ]);

        $this->info('Admin user created successfully!');
        $this->info("Email: {$user->email}");
        $this->info("Name: {$user->name}");

        return 0;
    }
}
