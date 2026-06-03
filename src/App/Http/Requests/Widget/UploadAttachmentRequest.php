<?php

namespace App\Http\Requests\Widget;

use Domain\Agent\Models\Agent;
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
                'max:25600', // 25 MB — Mistral OCR allows 50 MB but chat attachments rarely need that much.
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

    public function toData(Agent $agent, ?int $conversationId): UploadAttachmentData
    {
        $sessionId = (string) $this->header('X-SESSION-ID');

        return new UploadAttachmentData(
            file: $this->file('file'),
            directory: sprintf('widget-uploads/agent-%d/%s', $agent->id, $sessionId),
            conversationId: $conversationId,
        );
    }
}
