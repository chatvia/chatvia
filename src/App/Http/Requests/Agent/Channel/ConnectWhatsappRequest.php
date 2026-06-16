<?php

namespace App\Http\Requests\Agent\Channel;

use Domain\Channel\Whatsapp\Data\ConnectWhatsappData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Connect a WhatsApp business number to an agent via WhatsApp Embedded Signup:
 * the Facebook JS SDK popup returns an OAuth code plus the chosen WhatsApp
 * Business Account id and phone number id, which the browser POSTs here.
 * Authorization is the route's `agent.permission:agent.channels` middleware.
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
            'code' => ['required', 'string'],
            'waba_id' => ['required', 'string', 'regex:/^\d{5,20}$/'],
            'phone_number_id' => ['required', 'string', 'regex:/^\d{5,20}$/'],
        ];
    }

    public function toData(): ConnectWhatsappData
    {
        return new ConnectWhatsappData(
            code: $this->string('code')->toString(),
            wabaId: $this->string('waba_id')->toString(),
            phoneNumberId: $this->string('phone_number_id')->toString(),
        );
    }
}
