<?php

namespace App\Filament\Admin\Resources\TicketResource\Pages;

use App\Filament\Admin\Resources\TicketResource;
use App\Models\TicketReply;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class ManageTicket extends EditRecord
{
    protected static string $resource = TicketResource::class;

    protected function getRedirectUrl(): string
    {
        return TicketResource::getUrl('index');
    }

    public function getTitle(): string
    {
        return 'Manage Ticket';
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['status'] ?? '') === 'resolved' && empty($data['resolved_at'])) {
            $data['resolved_at'] = now();
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $reply = $this->form->getState()['reply_message'] ?? null;
        if (! empty($reply)) {
            TicketReply::create([
                'ticket_id' => $this->getRecord()->id,
                'user_id' => Auth::id(),
                'type' => 'staff',
                'message' => $reply,
                'is_internal' => false,
            ]);
        }

        Notification::make()
            ->title('Ticket updated')
            ->success()
            ->send();
    }
}
