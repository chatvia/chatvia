<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Enums\FunctionAuthType;
use Domain\Integration\Function\Enums\FunctionWebhookMethod;
use Domain\Integration\Function\Models\AgentFunction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Builds an enabled webhook function (POST, no auth, no approval) in a new
 * workspace unless `workspace_id` is given, {@see forOwner()} or
 * {@see attachedTo()}.
 *
 * @extends Factory<AgentFunction>
 */
class AgentFunctionFactory extends Factory
{
    #[\Override]
    protected $model = AgentFunction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'fn_'.Str::lower(Str::random(8));

        return [
            // Lazy: only creates a workspace when the caller doesn't pass one.
            'workspace_id' => fn (): int => User::factory()->create()->workspace()->id,
            'internal_name' => $name,
            'display_name' => null,
            'description' => fake()->sentence(),
            'parameters_schema' => ['type' => 'object', 'properties' => []],
            'webhook_url' => "https://hooks.example.test/{$name}",
            'webhook_method' => FunctionWebhookMethod::Post,
            'webhook_headers' => null,
            'auth_type' => FunctionAuthType::None,
            'auth_config' => null,
            'requires_approval' => false,
            'is_enabled' => true,
        ];
    }

    /** A function of the given owner's (or teammate's) workspace. */
    public function forOwner(User $user): static
    {
        return $this->state(fn (): array => ['workspace_id' => $user->workspace()->id]);
    }

    /** A function the visitor's call waits on until a teammate approves it. */
    public function requiringApproval(): static
    {
        return $this->state(fn (): array => ['requires_approval' => true]);
    }

    /** Switched off workspace-wide: it keeps its attachments but is offered to no agent. */
    public function disabled(): static
    {
        return $this->state(fn (): array => ['is_enabled' => false]);
    }

    /**
     * A function of the agent's workspace, attached to the agent through the
     * `agent_function` pivot.
     */
    public function attachedTo(Agent $agent): static
    {
        return $this
            ->state(fn (): array => ['workspace_id' => $agent->workspace_id])
            ->afterCreating(function (AgentFunction $function) use ($agent): void {
                $agent->functions()->attach($function->id);
            });
    }
}
