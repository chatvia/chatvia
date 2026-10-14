<?php

namespace App\Http\Requests\Chat;

use Domain\Conversation\Enums\ChatExportFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'messageId' => ['sometimes', 'nullable', 'string'],
            'type' => ['required', 'string', Rule::enum(ChatExportFormat::class)],
        ];
    }

    public function exportFormat(): ChatExportFormat
    {
        return $this->enum('type', ChatExportFormat::class) ?? ChatExportFormat::Text;
    }

    /** The one message to export, or null for the whole conversation. */
    public function messageId(): ?string
    {
        return $this->filled('messageId') ? $this->string('messageId')->toString() : null;
    }
}
