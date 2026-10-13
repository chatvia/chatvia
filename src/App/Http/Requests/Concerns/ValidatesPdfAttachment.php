<?php

namespace App\Http\Requests\Concerns;

use App\Http\Rules\DocumentChatAvailable;
use App\Http\Rules\MaxConversationAttachments;
use App\Http\Rules\MaxPdfPages;
use Domain\Llm\Services\DocumentLlm;

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
     * Resolved through the container, so the rules get their services injected.
     *
     * @return array<string, mixed>
     */
    public function rules(DocumentLlm $documentLlm): array
    {
        return [
            'file' => [
                'required',
                new DocumentChatAvailable($documentLlm),
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf',
                'max:'.$this->maxFileSizeInKb(),
                new MaxConversationAttachments(
                    conversationExternalId: $this->header('X-CONVERSATION-ID'),
                    max: (int) config('chatvia.agent.files.upload.max_files'),
                ),
                new MaxPdfPages(
                    max: (int) config('chatvia.agent.files.upload.max_pdf_pages'),
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
            'file.required' => __('No file was uploaded.'),
            'file.mimes' => __('Only PDF files are supported right now.'),
            'file.mimetypes' => __('Only PDF files are supported right now.'),
            'file.max' => __('File is too large — the limit is :size MB.', ['size' => (int) round($this->maxFileSizeInKb() / 1024)]),
        ];
    }

    /** The largest chat attachment, in KB (`chatvia.agent.files.upload.max_file_size_in_kb`). */
    private function maxFileSizeInKb(): int
    {
        return (int) config('chatvia.agent.files.upload.max_file_size_in_kb');
    }
}
