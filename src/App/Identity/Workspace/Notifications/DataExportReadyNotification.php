<?php

namespace App\Identity\Workspace\Notifications;

use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use Domain\Notification\Data\NotificationContentData;
use Domain\Notification\Data\NotificationMessageData;
use Domain\Notification\Enums\NotificationKind;
use Domain\Notification\Notifications\InboxNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Email (and broadcast) that a data export is ready to download. It also
 * lands in the person's notification center, opening the data export page,
 * or the download itself while owners may not open that page.
 */
class DataExportReadyNotification extends InboxNotification
{
    use Queueable;

    public function __construct(
        private readonly DataExport $export,
    ) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::DataExportReady;
    }

    /**
     * @return array<int, string>
     */
    protected function alsoVia(object $notifiable): array
    {
        return ['mail', 'broadcast'];
    }

    public function toInbox(User $notifiable): NotificationMessageData
    {
        // While owners may not open the data export page, the entry downloads the ZIP.
        $url = (bool) (config('chatvia.account.data_export') ?? true)
            ? route('account.data-export.edit', absolute: false)
            : route('account.data-export.download', $this->export, absolute: false);

        return $this->message(new NotificationContentData(url: $url));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $downloadUrl = route('account.data-export.download', $this->export);
        $expiresAt = $this->export->expires_at?->toDayDateTimeString();

        return (new MailMessage)
            ->subject(__('Your :app data export is ready', ['app' => 'ChatVia.ai']))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line(__('Your personal data export is ready to download.'))
            ->action(__('Download my data'), $downloadUrl)
            ->line(__('This link will work until :expiresAt (24 hours from now), after which the file will be deleted from our servers.', ['expiresAt' => $expiresAt]))
            ->line(__('If you did not request this export, please contact us right away.'));
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'export_id' => $this->export->id,
            'expires_at' => $this->export->expires_at?->toIso8601String(),
            'file_size' => $this->export->file_size,
            'download_url' => route('account.data-export.download', $this->export),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'export_id' => $this->export->id,
            'expires_at' => $this->export->expires_at?->toIso8601String(),
        ];
    }
}
