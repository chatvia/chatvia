<?php

namespace App\Http\Requests\Agent\Channel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Connect an Instagram professional account to an agent: the account's access
 * token and the Instagram app secret, both from the workspace's own Meta app
 * (Instagram API with Instagram Login). Authorization is the route's
 * `agent.permission:agent.setup` middleware.
 */
class ConnectInstagramRequest extends FormRequest
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
            'access_token' => ['required', 'string', 'min:30'],
            'app_secret' => ['required', 'string', 'min:20', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'access_token.min' => 'That doesn’t look like an Instagram access token (generate one under Instagram → API setup with Instagram login in your Meta app).',
            'app_secret.min' => 'That doesn’t look like an Instagram app secret (find it under Instagram → API setup with Instagram login → App secret).',
        ];
    }

    public function accessToken(): string
    {
        return $this->string('access_token')->toString();
    }

    public function appSecret(): string
    {
        return $this->string('app_secret')->toString();
    }
}
