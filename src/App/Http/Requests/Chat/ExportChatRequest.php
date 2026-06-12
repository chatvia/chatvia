<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a chat-export request. Per-conversation access is enforced by the
 * EnsureChatIsAccessible middleware on the `{conversationId}` route, so this
 * request only shapes the export inputs.
 */
class ExportChatRequest extends FormRequest
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
            'messageId' => ['sometimes'],
            'type' => ['required', 'string', 'in:pdf,text,markdown'],
        ];
    }
}
