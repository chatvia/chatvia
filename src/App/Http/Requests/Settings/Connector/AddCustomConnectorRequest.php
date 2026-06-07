<?php

namespace App\Http\Requests\Settings\Connector;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Rules\SafeWebhookUrl;
use Domain\Integration\Connector\Data\Custom\AddCustomConnectorData;
use Illuminate\Foundation\Http\FormRequest;

class AddCustomConnectorRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:64'],
            'url' => ['required', 'url', 'max:2048', new SafeWebhookUrl],
            'auth_strategy' => ['required', 'in:oauth,token,none'],
            'token' => ['sometimes', 'nullable', 'string', 'max:8192'],
        ];
    }

    public function toData(): AddCustomConnectorData
    {
        /** @var User $user */
        $user = $this->user();

        return new AddCustomConnectorData(
            user: $user,
            name: $this->string('name')->toString(),
            url: $this->string('url')->toString(),
            auth_strategy: $this->string('auth_strategy')->toString(),
            token: $this->input('token'),
        );
    }
}
