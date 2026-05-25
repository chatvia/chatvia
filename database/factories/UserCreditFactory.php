<?php

namespace Database\Factories;

use App\Billing\Models\UserCredit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserCredit>
 */
class UserCreditFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'messages' => $this->faker->numberBetween(50, 200),
            'started_at' => now()->subDays($this->faker->numberBetween(1, 30)),
            'ends_at' => now()->addDays($this->faker->numberBetween(1, 30)),
        ];
    }
}
