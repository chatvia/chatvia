<?php

namespace App\Http\Requests\Chat;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Conversations\CreateConversationData;
use Illuminate\Foundation\Http\FormRequest;

class CreateConversationRequest extends FormRequest
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
            'message' => ['required', 'string'],
            // Frontend sends the full AttachmentData objects it got from
            // the upload endpoint. We only need the `id`s server-side to
            // claim the orphan rows.
            'attachments' => ['nullable', 'array'],
            'attachments.*.id' => ['required', 'string'],
        ];
    }

    public function toData(Agent $agent): CreateConversationData
    {
        return new CreateConversationData(
            agent: $agent,
            message: $this->string('message')->toString(),
            ipAddress: $this->ip(),
            userAgent: $this->userAgent(),
            referrerUrl: $this->header('referer') ?: $this->server('HTTP_REFERER'),
            pageUrl: $this->url(),
            locale: $this->header('accept-language') ? substr($this->header('accept-language'), 0, 5) : null,
            attachmentIds: $this->extractAttachmentIds(),
        );
    }

    /**
     * @return array<int, string>
     */
    private function extractAttachmentIds(): array
    {
        $raw = $this->input('attachments', []);
        if (! is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $entry) {
            if (is_array($entry) && ! empty($entry['id'])) {
                $ids[] = (string) $entry['id'];
            }
        }

        return $ids;
    }
}
