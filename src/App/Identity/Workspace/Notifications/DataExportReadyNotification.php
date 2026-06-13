<?php

namespace App\Identity\Workspace\Notifications;

use App\Identity\Workspace\Models\DataExport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DataExportReadyNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly DataExport $export,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $downloadUrl = route('account.data-export.download', $this->export);
        $expiresAt = $this->export->expires_at?->toDayDateTimeString();

        return (new MailMessage)
            ->subject(__('Your ChatVia data export is ready'))
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
