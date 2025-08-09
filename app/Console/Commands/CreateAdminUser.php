<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create-user {email} {name} {password?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an admin user for the Filament panel';

    /**
     * Execute the console command.
     */
    public function handle()
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
            'is_admin' => true,
        ]);

        $this->info('Admin user created successfully!');
        $this->info("Email: {$user->email}");
        $this->info("Name: {$user->name}");
        $this->info('You can now login to the admin panel at: '.url('/admin'));

        return 0;
    }
}
