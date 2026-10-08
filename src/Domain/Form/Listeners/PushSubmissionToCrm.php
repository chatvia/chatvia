<?php

namespace Domain\Form\Listeners;

use Domain\Agent\Models\Agent;
use Domain\Form\Events\FormSubmitted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Support\Exceptions\UnsafeUrlException;
use Support\Http\SignedWebhookSender;

/**
 * Delivers a captured submission to the agent's configured CRM webhook — an
 * HMAC-SHA256-signed JSON POST sent through {@see SignedWebhookSender}, which
 * re-checks the URL against SSRF right before sending (save-time validation is
 * bypassable) and never follows redirects. No-op unless the agent has the
 * webhook enabled with a URL + secret. Throws on a non-2xx / transport error so
 * the queue retries; an unsafe URL is logged and dropped, since retrying cannot
 * fix it.
 */
class PushSubmissionToCrm implements ShouldQueue
{
    use InteractsWithQueue;

    /** Only deliver once the form-submit transaction has committed. */
    public bool $afterCommit = true;

    public function __construct(
        private readonly SignedWebhookSender $sender,
    ) {}

    public function handle(FormSubmitted $event): void
    {
        $agent = Agent::find($event->agentId);
        if ($agent === null) {
            return;
        }

        $crm = $agent->settings('leads')->toArray()['crm'] ?? [];
        $url = $crm['webhook_url'] ?? null;
        $secret = $crm['webhook_secret'] ?? null;

        if (empty($crm['webhook_enabled']) || blank($url) || blank($secret)) {
            return;
        }

        try {
            $this->sender->send(
                url: (string) $url,
                event: 'form.submitted',
                payload: $this->payload($agent, $event),
                secret: (string) $secret,
            );
        } catch (UnsafeUrlException $e) {
            Log::warning('PushSubmissionToCrm refused an unsafe webhook URL', [
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(FormSubmitted $event, \Throwable $e): void
    {
        Log::warning('PushSubmissionToCrm delivery failed', [
            'agent_id' => $event->agentId,
            'submission_id' => $event->submissionId,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Agent $agent, FormSubmitted $event): array
    {
        return [
            'event' => 'form.submitted',
            'form' => $event->formSlug,
            'form_type' => $event->formType,
            'source' => $event->source,
            'agent_id' => $agent->external_id,
            'conversation_id' => $event->conversationExternalId,
            'timestamp' => now()->toIso8601String(),
            'values' => $event->values,
        ];
    }
}
