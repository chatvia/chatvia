<?php

namespace App\Http\Requests\Agent\Channel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Connect a Facebook Page to an agent: the page access token and the Meta
 * app secret, both from the workspace's own Meta app. Authorization is the
 * route's `agent.permission:agent.setup` middleware.
 */
class ConnectMessengerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'page_access_token' => ['required', 'string', 'min:30'],
            'app_secret' => ['required', 'string', 'min:20', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'page_access_token.min' => 'That doesn’t look like a page access token (generate one under Messenger → API settings in your Meta app).',
            'app_secret.min' => 'That doesn’t look like a Meta app secret (find it under App settings → Basic in your Meta app).',
        ];
    }

    public function pageAccessToken(): string
    {
        return $this->string('page_access_token')->toString();
    }

    public function appSecret(): string
    {
        return $this->string('app_secret')->toString();
    }
}
