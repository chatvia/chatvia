<?php

namespace App\Http\Requests\Concerns;

use App\Http\Rules\ChatFilesAvailable;
use App\Http\Rules\MaxConversationAttachments;
use App\Http\Rules\MaxPdfPages;
use App\Http\Rules\UnlockedPdf;
use Domain\Llm\Services\ChatFileModel;

/**
 * Shared attachment validation for the chat and widget upload requests: a
 * PDF or an image, while chat file uploads are on ({@see ChatFileModel}).
 * Only the validation contract is shared — each request keeps its own
 * toData() (different storage path + identity headers).
 */
trait ValidatesChatAttachment
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
    public function rules(ChatFileModel $fileModel): array
    {
        return [
            'file' => [
                'bail',
                'required',
                new ChatFilesAvailable($fileModel),
                'file',
                'mimes:'.implode(',', $fileModel->acceptedExtensions()),
                'mimetypes:'.implode(',', $fileModel->acceptedMimeTypes()),
                'max:'.$this->maxFileSizeInKb(),
                new MaxConversationAttachments(
                    conversationExternalId: $this->header('X-CONVERSATION-ID'),
                    max: (int) config('chatvia.agent.files.upload.max_files'),
                ),
                new UnlockedPdf,
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
            'file.mimes' => __('Only PDF files and images (PNG, JPEG, WebP or GIF) can be attached.'),
            'file.mimetypes' => __('Only PDF files and images (PNG, JPEG, WebP or GIF) can be attached.'),
            'file.max' => __('File is too large — the limit is :size MB.', ['size' => (int) round($this->maxFileSizeInKb() / 1024)]),
        ];
    }

    /** The largest chat attachment, in KB (`chatvia.agent.files.upload.max_file_size_in_kb`). */
    private function maxFileSizeInKb(): int
    {
        return (int) config('chatvia.agent.files.upload.max_file_size_in_kb');
    }
}
