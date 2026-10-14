<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the browser's WebRTC offer for a voice call (hosted chat and
 * widget alike): plain SDP text, a few kilobytes long.
 */
class StartVoiceCallRequest extends FormRequest
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
            'sdp' => ['required', 'string', 'max:20000'],
        ];
    }

    public function sdp(): string
    {
        return $this->string('sdp')->toString();
    }
}
