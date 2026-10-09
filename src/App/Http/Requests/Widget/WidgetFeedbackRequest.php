<?php

namespace App\Http\Requests\Widget;

use Domain\Conversation\Enums\MessageFeedback;
use Domain\Widget\Data\Activity\WidgetFeedbackData;
use Domain\Widget\WidgetHeaders;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Thumbs-up / thumbs-down from the widget. The body carries `feedback`
 * (true = positive); the rated message is named by the X-MESSAGE-ID header,
 * which is copied into the input so a missing header fails validation.
 */
class WidgetFeedbackRequest extends FormRequest
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
            'feedback' => ['required', 'boolean'],
            'message_id' => ['required', 'string'],
        ];
    }

    public function toData(): WidgetFeedbackData
    {
        return new WidgetFeedbackData(
            messageId: $this->string('message_id')->toString(),
            feedback: $this->boolean('feedback') ? MessageFeedback::Positive : MessageFeedback::Negative,
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['message_id' => $this->header(WidgetHeaders::MESSAGE_ID)]);
    }
}
