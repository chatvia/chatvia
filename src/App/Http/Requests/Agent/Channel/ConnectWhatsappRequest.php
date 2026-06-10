<?php

namespace App\Http\Requests\Agent\Channel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Connect a WhatsApp business number to an agent: the Cloud API credentials
 * from the workspace's own Meta app (WhatsApp → API Setup shows the phone
 * number ID and WABA ID side by side). Authorization is the route's
 * `agent.permission:agent.setup` middleware.
 */
class ConnectWhatsappRequest extends FormRequest
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
            'phone_number_id' => ['required', 'string', 'regex:/^\d{5,20}$/'],
            'waba_id' => ['required', 'string', 'regex:/^\d{5,20}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'access_token.min' => 'That doesn’t look like a Cloud API access token (use a permanent System User token, not the 24-hour test token).',
            'app_secret.min' => 'That doesn’t look like a Meta app secret (find it under App settings → Basic in your Meta app).',
            'phone_number_id.regex' => 'The phone number ID is the numeric ID from WhatsApp → API Setup — not the phone number itself.',
            'waba_id.regex' => 'The WhatsApp Business Account ID is the numeric ID shown next to the phone number ID in API Setup.',
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

    public function phoneNumberId(): string
    {
        return $this->string('phone_number_id')->toString();
    }

    public function wabaId(): string
    {
        return $this->string('waba_id')->toString();
    }
}
