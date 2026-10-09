<?php

namespace Database\Factories;

use App\Identity\Workspace\Enums\UserSource;
use App\Identity\Workspace\Enums\UserType;
use App\Identity\Workspace\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    #[\Override]
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
            // Random prefix: the test suite runs on a persistent, seeded database,
            // so a plain Faker address can collide with a row left by seeding.
            'email' => Str::lower(Str::random(8)).'.'.fake()->unique()->safeEmail(),
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
            // Settled by default so existing tests don't trip the first-run
            // welcome tour; override with `['onboarded_at' => null]` to exercise it.
            'onboarded_at' => now(),
            'locale' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => UserType::Admin,
        ]);
    }

    public function workspace(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => UserType::Workspace,
        ]);
    }

    public function employee(?User $workspace = null): static
    {
        return $this->state(fn (array $attributes): array => [
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
        return $this->state(fn (array $attributes): array => [
            'type' => UserType::Chatter,
            'source' => UserSource::Api,
            'email' => null,
            'workspace_id' => $workspace?->id ?? User::factory()->workspace(),
        ]);
    }
}
