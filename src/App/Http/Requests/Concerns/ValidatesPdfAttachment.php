<?php

namespace App\Http\Requests\Concerns;

use Domain\Conversation\Rules\DocumentChatAvailable;
use Domain\Conversation\Rules\HasMessageCreditsForPdf;
use Domain\Conversation\Rules\MaxConversationAttachments;
use Domain\Conversation\Rules\MaxPdfPages;

/**
 * Shared PDF-attachment validation for the chat and widget upload requests.
 * Only the validation contract is shared — each request keeps its own
 * toData() (different storage path + identity headers) and resolves the owning
 * workspace differently (see {@see workspaceUserId()}).
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
                new DocumentChatAvailable,
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf',
                'max:25600', // 25 MB — chat attachments rarely need more.
                new MaxConversationAttachments(
                    conversationExternalId: $this->header('X-CONVERSATION-ID'),
                    max: (int) config('chatvia.agent.files.upload.max_files'),
                ),
                new MaxPdfPages(
                    max: (int) config('chatvia.agent.files.upload.max_pdf_pages'),
                ),
                new HasMessageCreditsForPdf(
                    workspaceUserId: $this->workspaceUserId(),
                ),
            ],
        ];
    }

    /**
     * Owning workspace (account) user id, used to gate the upload on the
     * remaining message balance. Each request resolves it from its own identity
     * source (X-AGENT-ID header vs. the widget's resolved agent).
     */
    abstract protected function workspaceUserId(): ?int;

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
