<?php

namespace Domain\Channel\Whatsapp\Actions;

use Domain\Agent\Models\Agent;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Domain\Channel\Whatsapp\Services\WhatsappClient;
use Illuminate\Support\Str;

/**
 * Connects (or reconnects) a WhatsApp business number to an agent: validates
 * the access token + phone number id with the Graph API, stores them
 * (encrypted) together with the Meta app secret and a fresh webhook secret,
 * and subscribes the Meta app to the WhatsApp Business Account (best-effort —
 * without the subscription the webhook never fires). Returns null when the
 * token/number pair is invalid so the controller can surface a field error.
 *
 * The app-level webhook itself can't be registered via API — the workspace
 * pastes the Callback URL + verify token (both embed `webhook_secret`) into
 * their Meta app's WhatsApp → Configuration settings; Meta then verifies it
 * with a GET handshake our webhook answers.
 */
class ConnectWhatsappNumberAction
{
    public function __construct(
        private readonly WhatsappClient $client,
    ) {}

    public function execute(
        Agent $agent,
        string $accessToken,
        string $appSecret,
        string $phoneNumberId,
        string $wabaId,
    ): ?AgentWhatsappNumber {
        $info = $this->client->numberInfo($accessToken, $phoneNumberId);

        if ($info === null) {
            return null;
        }

        $number = AgentWhatsappNumber::updateOrCreate(
            ['agent_id' => $agent->id],
            [
                'access_token' => $accessToken,
                'app_secret' => $appSecret,
                'phone_number_id' => $phoneNumberId,
                'waba_id' => $wabaId,
                'display_phone_number' => $info['display_phone_number'],
                'verified_name' => $info['verified_name'],
                'webhook_secret' => Str::random(48),
                'enabled' => true,
            ],
        );

        $this->client->subscribeWaba($accessToken, $wabaId);

        return $number;
    }
}
