<?php

namespace App\Http\Requests\Agent\Channel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Connect a Slack app to an agent: the app's bot token (xoxb-…) and signing
 * secret, both from the workspace's own Slack app. Authorization is the
 * route's `agent.permission:agent.setup` middleware.
 */
class ConnectSlackRequest extends FormRequest
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
            'bot_token' => ['required', 'string', 'regex:/^xoxb-[A-Za-z0-9-]{10,}$/'],
            'signing_secret' => ['required', 'string', 'min:20', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bot_token.regex' => 'That doesn’t look like a Slack bot token — it should start with xoxb- (find it under OAuth & Permissions in your Slack app).',
            'signing_secret.min' => 'That doesn’t look like a Slack signing secret (find it under Basic Information in your Slack app).',
        ];
    }

    public function botToken(): string
    {
        return $this->string('bot_token')->toString();
    }

    public function signingSecret(): string
    {
        return $this->string('signing_secret')->toString();
    }
}
