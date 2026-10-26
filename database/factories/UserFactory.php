<?php

namespace Database\Factories;

use App\Identity\Workspace\Enums\WorkspaceRole;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Builds a verified staff account that owns a new workspace. States turn it
 * into a teammate of another workspace or a platform admin (chatters have
 * their own ChatterFactory). The email gets a random prefix
 * because tests run against a seeded database whose rows could collide with
 * plain fake addresses.
 *
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
     * Define the model's default state: a verified staff account. The
     * workspace membership is added after creating ({@see configure()}).
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
            'is_admin' => false,
            // Explicitly seed nullable columns so the model is queryable
            // under Model::preventAccessingMissingAttributes() without an
            // extra refresh() call in every test.
            'current_team_id' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'registered_ip' => null,
            'registered_country_code' => null,
            'profile_image_path' => null,
            'deactivation_reason' => null,
            'deactivated_at' => null,
            'last_login_at' => null,
            'locale' => null,
            'onboarding_tour' => null,
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

    /**
     * Every user belongs to a workspace: by default its own, as the owner.
     * {@see employee()} plans a teammate membership instead (set while
     * making, applied once the user row exists).
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            $planned = $user->relationLoaded('factoryWorkspace') ? $user->getRelation('factoryWorkspace') : null;
            $user->unsetRelation('factoryWorkspace');

            if ($planned instanceof Workspace) {
                $planned->members()->attach($user->id, ['role' => WorkspaceRole::Member->value]);
            } else {
                Workspace::factory()->create(['name' => $user->name])
                    ->members()->attach($user->id, ['role' => WorkspaceRole::Owner->value]);
            }

            $user->unsetRelation('workspaceMembership');
        });
    }

    /** Platform staff: may open the admin panel. Owns a workspace like anyone. */
    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_admin' => true,
        ]);
    }

    /** The owner of a new workspace (the default). */
    public function workspace(): static
    {
        return $this;
    }

    /**
     * A teammate of the given owner's workspace (a new one when omitted).
     */
    public function employee(?User $owner = null): static
    {
        return $this->afterMaking(function (User $user) use ($owner): void {
            $user->setRelation('factoryWorkspace', ($owner ?? User::factory()->create())->workspace());
        });
    }
}
