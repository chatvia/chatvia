<?php

namespace Domain\Channel\Whatsapp\Actions;

use Domain\Agent\Models\Agent;
use Domain\Channel\Enums\MetaApp;
use Domain\Channel\Exceptions\ChannelConnectException;
use Domain\Channel\Services\ChannelConnectionGuard;
use Domain\Channel\Services\MetaOAuthClient;
use Domain\Channel\Whatsapp\Data\ConnectWhatsappData;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Domain\Channel\Whatsapp\Services\WhatsappClient;
use Illuminate\Support\Facades\Log;

/**
 * Connects (or reconnects) a WhatsApp business number to an agent after the
 * workspace granted it through WhatsApp Embedded Signup on Chatvia's Meta app,
 * so the workspace never pastes a Cloud API token or app secret. It
 * exchanges the popup's OAuth code for a business-integration token, subscribes
 * the WhatsApp Business Account to Chatvia's app (without the subscription the
 * app-level webhook never fires), registers the number for the Cloud API, then
 * stores the token (encrypted). Throws {@see ChannelConnectException} when the
 * agent is protected, the number already powers another agent, or the code
 * can't be exchanged / the number can't be read.
 *
 * Inbound events are routed back to this row by `phone_number_id` against the
 * single app-level webhook and the HMAC is verified with the shared app secret,
 * so no per-number `app_secret`/`webhook_secret` is collected.
 */
class ConnectWhatsappNumberAction
{
    public function __construct(
        private readonly MetaOAuthClient $oauth,
        private readonly WhatsappClient $client,
        private readonly ChannelConnectionGuard $guard,
    ) {}

    public function execute(Agent $agent, ConnectWhatsappData $data): AgentWhatsappNumber
    {
        $this->guard->ensureAgentIsPublic($agent);
        $this->guard->ensureAccountIsFree($agent, AgentWhatsappNumber::query()->where('phone_number_id', $data->phoneNumberId));

        $token = $this->oauth->exchangeEmbeddedSignupCode($data->code);
        $info = $token !== null ? $this->client->numberInfo($token, $data->phoneNumberId) : null;

        if ($token === null || $info === null) {
            throw ChannelConnectException::rejected();
        }

        $this->prepareNumber($agent, $token, $data);

        // The partial unique index on (phone_number_id) WHERE enabled is the
        // real guard behind the up-front check when two agents race.
        return $this->guard->store(fn (): AgentWhatsappNumber => AgentWhatsappNumber::updateOrCreate(
            ['agent_id' => $agent->id],
            [
                'access_token' => $token,
                'phone_number_id' => $data->phoneNumberId,
                'waba_id' => $data->wabaId,
                'display_phone_number' => $info['display_phone_number'],
                'verified_name' => $info['verified_name'],
                'is_enabled' => true,
            ],
        ));
    }

    /**
     * Subscribe the WhatsApp Business Account to the app and register the
     * number for the Cloud API — both best-effort.
     */
    private function prepareNumber(Agent $agent, string $token, ConnectWhatsappData $data): void
    {
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

        $pin = MetaApp::Whatsapp->registerPin();

        if ($pin !== null) {
            $this->client->registerNumber($token, $data->phoneNumberId, $pin);
        } else {
            Log::info('WhatsApp connect: no WhatsApp registration PIN set (admin panel: Settings → Channels) — skipping Cloud API number registration', [
                'agent_id' => $agent->id,
                'phone_number_id' => $data->phoneNumberId,
            ]);
        }
    }
}
