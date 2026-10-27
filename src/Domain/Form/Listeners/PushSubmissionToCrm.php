<?php

namespace Domain\Form\Listeners;

use Domain\Agent\Models\Agent;
use Domain\Form\Actions\RecordCrmDeliveryFailureAction;
use Domain\Form\Events\FormSubmitted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
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
 * the queue retries; an unsafe URL fails at once, since retrying cannot fix it.
 * Once retries are used up, failed() counts the failure and
 * {@see RecordCrmDeliveryFailureAction} switches a dead endpoint off; a
 * successful delivery resets the counter.
 */
// A CRM endpoint that fails once often answers on the next try.
#[Tries(3)]
// Longer and longer waits, so a restarting endpoint has time to come back.
#[Backoff(10, 60, 300)]
class PushSubmissionToCrm implements ShouldQueue
{
    use InteractsWithQueue;

    /** Only deliver once the form-submit transaction has committed. */
    public bool $afterCommit = true;

    public function __construct(
        private readonly SignedWebhookSender $sender,
        private readonly RecordCrmDeliveryFailureAction $recordFailure,
    ) {}

    public function handle(FormSubmitted $event): void
    {
        $agent = Agent::query()->find($event->submission->agentId);
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
            // Retrying cannot make the URL safe, so burn no tries: fail now,
            // which counts towards the auto-disable threshold in failed().
            if ($this->job === null) {
                throw $e;
            }

            $this->fail($e);

            return;
        }

        // A delivered webhook proves the endpoint lives again.
        if ((int) ($crm['webhook_failures'] ?? 0) > 0) {
            $crm['webhook_failures'] = 0;
            $agent->setSetting('crm', $crm, 'leads');
        }
    }

    public function failed(FormSubmitted $event, \Throwable $e): void
    {
        Log::warning('PushSubmissionToCrm delivery failed', [
            'agent_id' => $event->submission->agentId,
            'submission_id' => $event->submission->submissionId,
            'error' => $e->getMessage(),
        ]);

        $agent = Agent::query()->find($event->submission->agentId);
        if ($agent !== null) {
            $this->recordFailure->execute($agent);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Agent $agent, FormSubmitted $event): array
    {
        $submission = $event->submission;

        return [
            'event' => 'form.submitted',
            // The same on every retry, so the receiver can drop a repeat.
            'delivery_id' => $event->deliveryId,
            'form' => $submission->formSlug,
            'form_type' => $submission->formType->value,
            'source' => $submission->source,
            'agent_id' => $agent->external_id,
            'conversation_id' => $submission->conversationExternalId,
            'timestamp' => now()->toIso8601String(),
            'values' => $submission->values,
        ];
    }
}
