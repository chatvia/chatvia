<?php

namespace Domain\Form\Listeners;

use Domain\Agent\Models\Agent;
use Domain\Form\Events\FormSubmitted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Support\Exceptions\UnsafeUrlException;
use Support\Http\SafeUrl;

/**
 * Delivers a captured submission to the agent's configured CRM webhook — an
 * HMAC-SHA256-signed JSON POST. No-op unless the agent has the webhook enabled
 * with a URL + secret. Throws on a non-2xx / transport error so the queue
 * retries. SSRF re-checked at dispatch (save-time validation is bypassable).
 */
class PushSubmissionToCrm implements ShouldQueue
{
    use InteractsWithQueue;

    /** Only deliver once the form-submit transaction has committed. */
    public bool $afterCommit = true;

    private const TIMEOUT_SECONDS = 10;

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
            SafeUrl::assert((string) $url);
        } catch (UnsafeUrlException $e) {
            Log::warning('PushSubmissionToCrm refused an unsafe webhook URL', [
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $body = (string) json_encode($this->payload($agent, $event));
        $signature = hash_hmac('sha256', $body, (string) $secret);

        Http::timeout(self::TIMEOUT_SECONDS)
            ->withOptions(['allow_redirects' => false])
            ->withHeaders([
                'X-Chatvia-Event' => 'form.submitted',
                'X-Chatvia-Signature' => 'sha256='.$signature,
            ])
            ->withBody($body, 'application/json')
            ->post((string) $url)
            ->throw();
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
