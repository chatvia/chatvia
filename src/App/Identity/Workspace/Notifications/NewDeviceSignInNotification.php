<?php

namespace App\Identity\Workspace\Notifications;

use App\Identity\Workspace\Models\User;
use Domain\Analytics\Services\UserAgentParser;
use Domain\Notification\Data\NotificationContentData;
use Domain\Notification\Data\NotificationMessageData;
use Domain\Notification\Enums\NotificationKind;
use Domain\Notification\Notifications\InboxNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * Heads-up email sent the first time we see a user sign in from a given
 * (IP, User-Agent) combination. The link points at /settings/password so the
 * recipient can lock the account down if it wasn't them. It also lands in the
 * person's notification center, naming the browser and system.
 */
class NewDeviceSignInNotification extends InboxNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?string $ip,
        public ?string $userAgent,
        public Carbon $signedInAt,
    ) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::NewDeviceSignIn;
    }

    /**
     * @return array<int, string>
     */
    protected function alsoVia(object $notifiable): array
    {
        return ['mail'];
    }

    public function toInbox(User $notifiable): NotificationMessageData
    {
        $device = app(UserAgentParser::class)->detect($this->userAgent);
        $name = Arr::join(array_filter([$device->browser, $device->os]), ' · ');

        return $this->message(new NotificationContentData(
            url: route('account.password.edit', absolute: false),
            subject: $name !== '' ? $name : null,
        ));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New sign-in to your account'))
            ->greeting(__('Hello!'))
            ->line(__('We noticed a sign-in to your account from a device we haven\'t seen before.'))
            ->line(__('**When:** :when UTC', ['when' => $this->signedInAt->toDayDateTimeString()]))
            ->line(__('**IP address:** :ip', ['ip' => $this->ip ?? __('unknown')]))
            ->line(__('**Browser / device:** :device', ['device' => $this->userAgent ?? __('unknown')]))
            ->line(__('If this was you, no action is needed.'))
            ->action(__('Review your password'), url(route('account.password.edit', absolute: true)))
            ->line(__('If this wasn\'t you, change your password immediately and review your two-factor settings.'));
    }
}
