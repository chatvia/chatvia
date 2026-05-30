<?php

namespace Database\Factories;

use App\Auth\Models\User;
use Domain\Agents\Models\AgentTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentTemplate>
 */
class AgentTemplateFactory extends Factory
{
    protected $model = AgentTemplate::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'is_public' => false,
            'hosted_chat_config' => null,
            'widget_config' => null,
            'bar_config' => null,
        ];
    }
}
