<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Settings;

use Domain\Agent\Data\Settings\AppearanceTextData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the Setup → appearance text form (chat title / welcome / composer
 * placeholder / notice under the composer). The route already enforces `agent.permission:agent.setup`, so
 * authorize() defers to it.
 */
class SaveAppearanceTextRequest extends FormRequest
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
            'chat_window_title' => ['nullable', 'string', 'max:120'],
            'welcome_message' => ['nullable', 'string', 'max:500'],
            'composer_placeholder' => ['nullable', 'string', 'max:200'],
            'notice' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function toData(): AppearanceTextData
    {
        return new AppearanceTextData(
            welcome_message: (string) $this->string('welcome_message'),
            chat_window_title: (string) $this->string('chat_window_title'),
            composer_placeholder: (string) $this->string('composer_placeholder'),
            notice: (string) $this->string('notice'),
        );
    }
}
