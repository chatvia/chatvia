<?php

use Domain\Agent\Models\Agent;
use Domain\Form\Data\CapturedSubmissionData;
use Domain\Form\Enums\FormType;
use Domain\Form\Events\FormSubmitted;
use Domain\Form\Listeners\PushSubmissionToCrm;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Support\Http\WebhookSignature;

/**
 * The CRM webhook listener posts a captured submission, signed with the
 * agent's secret, to the agent's webhook URL. It does nothing unless the
 * webhook is on with a URL and a secret, drops an unsafe URL (a retry cannot
 * fix it) and throws on a failed delivery so the queue retries.
 */
const PSTC_URL = 'https://crm.example.com/hooks/leads';

function pstcEvent(int $agentId): FormSubmitted
{
    return new FormSubmitted(new CapturedSubmissionData(
        agentId: $agentId,
        workspaceId: 7,
        submissionId: 42,
        formSlug: 'book-a-demo',
        formType: FormType::Booking,
        conversationExternalId: '0199b2c4-0000-7000-8000-000000000001',
        created: true,
        source: 'form',
        values: ['email' => 'jane@example.com', 'seats' => 5],
    ));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function pstcAgentWithCrm(array $overrides = []): Agent
{
    $agent = Agent::factory()->create();
    $agent->setSetting('crm', [
        'webhook_enabled' => true,
        'webhook_url' => PSTC_URL,
        'webhook_secret' => 'shh',
        ...$overrides,
    ], 'leads');

    return $agent;
}

it('posts the submission to the CRM webhook', function (): void {
    $this->freezeTime();
    $agent = pstcAgentWithCrm();
    Http::fake([PSTC_URL => Http::response('', 200)]);

    app(PushSubmissionToCrm::class)->handle(pstcEvent($agent->id));

    Http::assertSent(fn (Request $request): bool => $request->url() === PSTC_URL
        && $request->method() === 'POST'
        && $request->data() === [
            'event' => 'form.submitted',
            'form' => 'book-a-demo',
            'form_type' => 'booking',
            'source' => 'form',
            'agent_id' => $agent->external_id,
            'conversation_id' => '0199b2c4-0000-7000-8000-000000000001',
            'timestamp' => now()->toIso8601String(),
            'values' => ['email' => 'jane@example.com', 'seats' => 5],
        ]);
});

it('signs the body it sends with the agent\'s webhook secret', function (): void {
    $agent = pstcAgentWithCrm();
    Http::fake([PSTC_URL => Http::response('', 200)]);

    app(PushSubmissionToCrm::class)->handle(pstcEvent($agent->id));

    Http::assertSent(fn (Request $request): bool => $request->header('X-Chatvia-Event') === ['form.submitted']
        && $request->header('X-Chatvia-Signature') === ['sha256='.WebhookSignature::sign($request->body(), 'shh')]);
});

it('sends nothing unless the webhook is on with a URL and a secret', function (array $crm): void {
    $agent = pstcAgentWithCrm($crm);
    Http::fake();

    app(PushSubmissionToCrm::class)->handle(pstcEvent($agent->id));

    Http::assertNothingSent();
})->with([
    'turned off' => [['webhook_enabled' => false]],
    'no URL' => [['webhook_url' => '']],
    'no secret' => [['webhook_secret' => null]],
]);

it('sends nothing for an agent without CRM settings or one that is gone', function (): void {
    $agent = Agent::factory()->create();
    Http::fake();

    app(PushSubmissionToCrm::class)->handle(pstcEvent($agent->id));
    app(PushSubmissionToCrm::class)->handle(pstcEvent(999_999_999));

    Http::assertNothingSent();
});

it('logs and drops an unsafe webhook URL instead of retrying', function (): void {
    // The send-time SSRF check runs outside local and testing.
    app()['env'] = 'production';
    $agent = pstcAgentWithCrm(['webhook_url' => 'http://169.254.169.254/latest/meta-data']);
    Http::fake();
    Log::spy();

    // Returning normally (no exception) is what keeps the queue from retrying.
    app(PushSubmissionToCrm::class)->handle(pstcEvent($agent->id));

    Http::assertNothingSent();
    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'PushSubmissionToCrm refused an unsafe webhook URL'
            && $context['agent_id'] === $agent->id)
        ->once();
});

it('throws on a failed delivery so the queue retries', function (): void {
    $agent = pstcAgentWithCrm();
    Http::fake([PSTC_URL => Http::response('', 500)]);

    expect(fn () => app(PushSubmissionToCrm::class)->handle(pstcEvent($agent->id)))
        ->toThrow(RequestException::class);
});
