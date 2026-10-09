<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Billing\Models\UserAddon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds a one-month extra-messages add-on (1,000 messages). The caller sets
 * `user_id`.
 *
 * @extends Factory<UserAddon>
 */
class UserAddonFactory extends Factory
{
    #[\Override]
    protected $model = UserAddon::class;

    public function definition(): array
    {
        return [
            'type' => 'extra_message_credits',
            'amount' => 1000,
            'stripe_id' => 'cs_test_'.fake()->bothify('??????????'),
            'started_at' => now(),
            'ends_at' => now()->addMonth(),
        ];
    }
}
