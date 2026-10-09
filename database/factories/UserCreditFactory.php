<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Billing\Models\UserCredit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds a message-credit period that is active today, with a random message
 * allowance. The caller sets `user_id`.
 *
 * @extends Factory<UserCredit>
 */
class UserCreditFactory extends Factory
{
    #[\Override]
    protected $model = UserCredit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'messages' => fake()->numberBetween(50, 200),
            'started_at' => now()->subDays(fake()->numberBetween(1, 30)),
            'ends_at' => now()->addDays(fake()->numberBetween(1, 30)),
        ];
    }
}
