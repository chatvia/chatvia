<?php

namespace Database\Factories;

use App\Identity\Workspace\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Support\Enums\User\UserSource;
use Support\Enums\User\UserType;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

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
            // Explicitly seed nullable columns so the model is queryable
            // under Model::preventAccessingMissingAttributes() without an
            // extra refresh() call in every test.
            'workspace_id' => null,
            'current_team_id' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'registered_ip' => null,
            'registered_country_code' => null,
            'domain' => null,
            'profile_image_path' => null,
            'deactivation_reason' => null,
            'deactivated_at' => null,
            'last_login_at' => null,
            'locale' => null,
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
