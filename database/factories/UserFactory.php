<?php

namespace Database\Factories;

use App\Auth\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Support\Enums\UserSource;
use Support\Enums\UserType;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state — a `workspace` user (account owner),
     * registered via the dashboard.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_id' => (string) Str::uuid(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'type' => UserType::Workspace,
            'source' => UserSource::Dashboard,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserType::Admin,
        ]);
    }

    public function workspace(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserType::Workspace,
        ]);
    }

    public function employee(?User $workspace = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserType::Employee,
            'workspace_id' => $workspace?->id ?? User::factory()->workspace(),
        ]);
    }

    /**
     * A chat consumer — type=chatter, source=api, no login email by default
     * (matches how the public API provisions them).
     */
    public function chatter(?User $workspace = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserType::Chatter,
            'source' => UserSource::Api,
            'email' => null,
            'workspace_id' => $workspace?->id ?? User::factory()->workspace(),
        ]);
    }
}
