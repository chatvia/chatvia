<?php

namespace App\Http\Requests\Concerns;

use Domain\Conversation\Rules\MaxConversationAttachments;

/**
 * Shared PDF-attachment validation for the chat and widget upload requests.
 * Only the validation contract is shared — each request keeps its own
 * toData() (different storage path + identity headers).
 */
trait ValidatesPdfAttachment
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
            'file' => [
                'required',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf',
                'max:25600', // 25 MB — Mistral OCR allows 50 MB but chat attachments rarely need that much.
                new MaxConversationAttachments(
                    conversationExternalId: $this->header('X-CONVERSATION-ID'),
                    max: (int) config('chatvia.agent.files.upload.max_files'),
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'No file was uploaded.',
            'file.mimes' => 'Only PDF files are supported right now.',
            'file.mimetypes' => 'Only PDF files are supported right now.',
            'file.max' => 'File is too large — the limit is 25 MB.',
        ];
    }
}
