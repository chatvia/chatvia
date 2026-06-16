<?php

namespace Domain\Channel\Whatsapp\Actions;

use Domain\Agent\Models\Agent;
use Domain\Channel\Services\MetaOAuthClient;
use Domain\Channel\Whatsapp\Data\ConnectWhatsappData;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Domain\Channel\Whatsapp\Services\WhatsappClient;
use Illuminate\Support\Facades\Log;

/**
 * Connects (or reconnects) a WhatsApp business number to an agent after the
 * workspace granted it through WhatsApp Embedded Signup — one shared Chatvia
 * Meta app, so customers no longer paste a Cloud API token + app secret. It
 * exchanges the popup's OAuth code for a business-integration token, subscribes
 * the WhatsApp Business Account to Chatvia's app (without the subscription the
 * app-level webhook never fires), registers the number for the Cloud API, then
 * stores the token (encrypted). Returns null when the code can't be exchanged
 * or the number can't be read, so the controller can surface a field error.
 *
 * Inbound events are routed back to this row by `phone_number_id` against the
 * single app-level webhook and the HMAC is verified with the shared app secret,
 * so the per-number `app_secret`/`webhook_secret` are no longer collected.
 */
class ConnectWhatsappNumberAction
{
    public function __construct(
        private readonly MetaOAuthClient $oauth,
        private readonly WhatsappClient $client,
    ) {}

    public function execute(Agent $agent, ConnectWhatsappData $data): ?AgentWhatsappNumber
    {
        $token = $this->oauth->exchangeEmbeddedSignupCode($data->code);

        if ($token === null) {
            return null;
        }

        $info = $this->client->numberInfo($token, $data->phoneNumberId);

        if ($info === null) {
            return null;
        }

        // Best-effort side effects before the upsert (the number may already be
        // subscribed/registered from the Embedded Signup flow itself). They log
        // and no-op on a Meta error rather than blocking the connection — but the
        // WABA→app subscription is what makes inbound messages reach the app-level
        // webhook, so a failure here means a number that connects yet silently
        // never receives. Don't hard-fail (ES usually auto-subscribes), but flag
        // it loudly so a "connected but dead" number is diagnosable.
        if (! $this->client->subscribeWaba($token, $data->wabaId)) {
            Log::warning('WhatsApp connect: WABA subscription did not confirm — the number may not receive messages', [
                'agent_id' => $agent->id,
                'waba_id' => $data->wabaId,
                'phone_number_id' => $data->phoneNumberId,
            ]);
        }

        $pin = (string) config('services.meta.whatsapp_register_pin');

        if ($pin !== '') {
            $this->client->registerNumber($token, $data->phoneNumberId, $pin);
        } else {
            Log::info('WhatsApp connect: no META_WHATSAPP_REGISTER_PIN set — skipping Cloud API number registration', [
                'agent_id' => $agent->id,
                'phone_number_id' => $data->phoneNumberId,
            ]);
        }

        return AgentWhatsappNumber::updateOrCreate(
            ['agent_id' => $agent->id],
            [
                'access_token' => $token,
                'phone_number_id' => $data->phoneNumberId,
                'waba_id' => $data->wabaId,
                'display_phone_number' => $info['display_phone_number'],
                'verified_name' => $info['verified_name'],
                'enabled' => true,
            ],
        );
    }
}
