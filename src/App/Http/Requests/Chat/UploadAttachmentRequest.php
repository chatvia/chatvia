<?php

namespace App\Http\Requests\Chat;

use Domain\Conversation\Data\Attachments\UploadAttachmentData;
use Illuminate\Foundation\Http\FormRequest;

class UploadAttachmentRequest extends FormRequest
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
                'max:25600',
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

    public function toData(?int $conversationId): UploadAttachmentData
    {
        $agentId = (int) ($this->header('X-AGENT-ID') ?: 0);
        $conversationExternalId = (string) ($this->header('X-CONVERSATION-ID') ?: 'pending');

        return new UploadAttachmentData(
            file: $this->file('file'),
            directory: sprintf('chat-uploads/agent-%d/%s', $agentId, $conversationExternalId),
            conversationId: $conversationId,
        );
    }
}
