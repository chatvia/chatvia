<?php

namespace Database\Factories;

use App\Identity\Chatter\Enums\ChatterSource;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Chatters for tests: a workspace end-user with a verified email and a known
 * password, in a fresh workspace unless one is given.
 *
 * @extends Factory<Chatter>
 */
class ChatterFactory extends Factory
{
    #[\Override]
    protected $model = Chatter::class;

    /** One hash for every chatter: hashing is slow and the value is the same. */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_id' => (string) Str::uuid7(),
            'workspace_id' => fn (): int => User::factory()->create()->workspace()->id,
            'name' => fake()->name(),
            // Random prefix: the test suite runs on a persistent, seeded database,
            // so a plain Faker address can collide with a row left by seeding.
            'email' => Str::lower(Str::random(8)).'.'.fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'source' => ChatterSource::Api,
            // Nullable columns seeded explicitly so the model is queryable under
            // Model::preventAccessingMissingAttributes() without a refresh().
            'locale' => null,
            'profile_image_path' => null,
            'deactivation_reason' => null,
            'deactivated_at' => null,
            'last_login_at' => null,
            'remember_token' => null,
        ];
    }

    /**
     * A chatter of this workspace (given as the workspace or one of its staff).
     */
    public function ofWorkspace(User|Workspace $workspace): static
    {
        return $this->state(fn (): array => ['workspace_id' => $workspace instanceof User ? $workspace->workspace()->id : $workspace->id]);
    }

    /**
     * A chatter of the agent's workspace, granted that agent.
     */
    public function grantedTo(Agent $agent): static
    {
        return $this->state(fn (): array => ['workspace_id' => $agent->workspace_id])
            ->afterCreating(fn (Chatter $chatter) => $chatter->agents()->attach($agent->id, ['workspace_id' => $agent->workspace_id]));
    }

    public function deactivated(): static
    {
        return $this->state(fn (): array => ['deactivated_at' => now()]);
    }
}
