<?php

namespace Database\Factories;

use App\Identity\Workspace\Models\User;
use Domain\Notification\Data\NotificationContentData;
use Domain\Notification\Enums\NotificationKind;
use Domain\Notification\Models\UserNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds an unread "source trained" notification for a fresh user.
 *
 * @extends Factory<UserNotification>
 */
class UserNotificationFactory extends Factory
{
    #[\Override]
    protected $model = UserNotification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kind' => NotificationKind::SourceTrained,
            'category' => NotificationKind::SourceTrained->category(),
            'content' => new NotificationContentData(url: '/dashboard', subject: 'guide.pdf'),
        ];
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes): array => ['read_at' => now()]);
    }

    public function ofKind(NotificationKind $kind): static
    {
        return $this->state(fn (array $attributes): array => ['kind' => $kind, 'category' => $kind->category()]);
    }
}
