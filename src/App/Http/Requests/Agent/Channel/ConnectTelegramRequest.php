<?php

namespace App\Http\Requests\Agent\Channel;

use Domain\Channel\Telegram\Data\ConnectTelegramData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a Telegram bot token before connecting it to an agent. The route
 * already enforces `agent.permission:agent.channels`, so authorize() defers to it.
 */
class ConnectTelegramRequest extends FormRequest
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
            // A Telegram bot token looks like `123456789:ABCdef...` (≥35 token chars).
            'bot_token' => ['required', 'string', 'regex:/^\d+:[A-Za-z0-9_-]{30,}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bot_token.regex' => __('That doesn’t look like a Telegram bot token — it should look like 123456789:ABCdef… (get one from @BotFather).'),
        ];
    }

    public function toData(): ConnectTelegramData
    {
        return new ConnectTelegramData(botToken: $this->string('bot_token')->toString());
    }
}
