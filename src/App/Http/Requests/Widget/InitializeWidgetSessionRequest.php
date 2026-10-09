<?php

namespace App\Http\Requests\Widget;

use Domain\Widget\Data\Session\WidgetSessionInitData;
use Domain\Widget\WidgetHeaders;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Widget boot call. The session and conversation the widget remembered come
 * in headers; the page the widget runs on comes in `page_url`. The rest is
 * request metadata stored on a newly started session.
 */
class InitializeWidgetSessionRequest extends FormRequest
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
            'page_url' => ['nullable', 'string'],
        ];
    }

    public function toData(): WidgetSessionInitData
    {
        return new WidgetSessionInitData(
            existingSessionId: $this->header(WidgetHeaders::SESSION_ID),
            existingConversationId: $this->header(WidgetHeaders::CONVERSATION_ID),
            ipAddress: $this->ip(),
            userAgent: $this->userAgent(),
            acceptLanguage: $this->header('Accept-Language'),
            referrerUrl: $this->header('Referer'),
            pageUrl: $this->input('page_url'),
        );
    }
}
