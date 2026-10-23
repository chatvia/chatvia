<?php

use Domain\Agent\Models\Agent;
use Domain\Form\Data\CapturedSubmissionData;
use Domain\Form\Enums\FormType;
use Domain\Form\Events\FormSubmitted;
use Domain\Form\Listeners\PushSubmissionToCrm;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Support\Exceptions\UnsafeUrlException;
use Support\Http\WebhookSignature;

/**
 * The CRM webhook listener posts a captured submission, signed with the
 * agent's secret, to the agent's webhook URL. It does nothing unless the
 * webhook is on with a URL and a secret, fails fast on an unsafe URL (a retry
 * cannot fix it), throws on a failed delivery so the queue retries, and once
 * retries are used up failed() counts the failure towards auto-disable.
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

    $event = pstcEvent($agent->id);

    app(PushSubmissionToCrm::class)->handle($event);

    Http::assertSent(fn (Request $request): bool => $request->url() === PSTC_URL
        && $request->method() === 'POST'
        && $request->data() === [
            'event' => 'form.submitted',
            'delivery_id' => $event->deliveryId,
            'form' => 'book-a-demo',
            'form_type' => 'booking',
            'source' => 'form',
            'agent_id' => $agent->external_id,
            'conversation_id' => '0199b2c4-0000-7000-8000-000000000001',
            'timestamp' => now()->toIso8601String(),
            'values' => ['email' => 'jane@example.com', 'seats' => 5],
        ]);
});

it('sends the same delivery id when the queue retries the event', function (): void {
    $agent = pstcAgentWithCrm();
    Http::fake([PSTC_URL => Http::response('', 200)]);
    $event = pstcEvent($agent->id);

    // A retry runs the listener again on the event as the queue stored it.
    app(PushSubmissionToCrm::class)->handle($event);
    app(PushSubmissionToCrm::class)->handle(unserialize(serialize($event)));

    $ids = Http::recorded()->map(fn (array $pair): mixed => $pair[0]->data()['delivery_id']);

    expect($ids->all())->toBe([$event->deliveryId, $event->deliveryId])
        ->and(Str::isUuid($event->deliveryId))->toBeTrue();
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

it('fails fast on an unsafe webhook URL instead of retrying', function (): void {
    // The send-time SSRF check runs outside local and testing.
    app()['env'] = 'production';
    $agent = pstcAgentWithCrm(['webhook_url' => 'http://169.254.169.254/latest/meta-data']);
    Http::fake();

    // Off the queue the exception escapes; on the queue the same path fails
    // the job at once, which counts towards the auto-disable threshold.
    expect(fn () => app(PushSubmissionToCrm::class)->handle(pstcEvent($agent->id)))
        ->toThrow(UnsafeUrlException::class);

    Http::assertNothingSent();
});

it('throws on a failed delivery so the queue retries', function (): void {
    $agent = pstcAgentWithCrm();
    Http::fake([PSTC_URL => Http::response('', 500)]);

    expect(fn () => app(PushSubmissionToCrm::class)->handle(pstcEvent($agent->id)))
        ->toThrow(RequestException::class);
});

it('resets the failure counter after a successful delivery', function (): void {
    $agent = pstcAgentWithCrm(['webhook_failures' => 3]);
    Http::fake([PSTC_URL => Http::response(['ok' => true])]);

    app(PushSubmissionToCrm::class)->handle(pstcEvent($agent->id));

    expect($agent->fresh()->settings('leads')->get('crm')['webhook_failures'])->toBe(0);
});

it('counts a spent delivery towards the auto-disable threshold in failed()', function (): void {
    $agent = pstcAgentWithCrm();

    app(PushSubmissionToCrm::class)->failed(pstcEvent($agent->id), new RuntimeException('down'));

    expect($agent->fresh()->settings('leads')->get('crm')['webhook_failures'])->toBe(1);
});
