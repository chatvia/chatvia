<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The WebSocket client's request to listen on a channel from the hosted
 * chat: the channel it wants and its connection id. Whether the visitor may
 * is decided by the controller from their own chat session.
 */
class BroadcastAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'channel_name' => ['required', 'string', 'max:200'],
            'socket_id' => ['required', 'string', 'max:100'],
        ];
    }

    public function channelName(): string
    {
        return $this->string('channel_name')->toString();
    }

    public function socketId(): string
    {
        return $this->string('socket_id')->toString();
    }
}
