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
 * retries are used up failed() counts the failure towards auto-disable. A
 * retry sends the same body and delivery id, and a success resets the count.
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

it('posts the submission signed with the agent\'s secret, the same on a retry, and resets the failure count', function (): void {
    $this->freezeTime();
    $agent = pstcAgentWithCrm(['webhook_failures' => 3]);
    Http::fake([PSTC_URL => Http::response('', 200)]);
    $event = pstcEvent($agent->id);

    app(PushSubmissionToCrm::class)->handle($event);
    app(PushSubmissionToCrm::class)->handle(unserialize(serialize($event)));

    $requests = Http::recorded()->map(fn (array $pair): Request => $pair[0]);

    expect($requests)->toHaveCount(2)
        ->and($requests->every(fn (Request $request): bool => $request->url() === PSTC_URL
            && $request->method() === 'POST'
            && $request->header('X-Chatvia-Event') === ['form.submitted']
            && $request->header('X-Chatvia-Signature') === ['sha256='.WebhookSignature::sign($request->body(), 'shh')]))->toBeTrue()
        ->and($requests[1]->data())->toBe($requests[0]->data())
        ->and($requests[0]->data()['delivery_id'])->toBe($event->deliveryId)
        ->and($requests[0]->data()['values'])->toBe(['email' => 'jane@example.com', 'seats' => 5])
        ->and(Str::isUuid($event->deliveryId))->toBeTrue()
        ->and($agent->fresh()->settings('leads')->get('crm')['webhook_failures'])->toBe(0);
});

it('sends nothing unless the agent has the webhook on with a URL and a secret', function (Closure $agentId): void {
    Http::fake();

    app(PushSubmissionToCrm::class)->handle(pstcEvent($agentId()));

    Http::assertNothingSent();
})->with([
    'turned off' => [fn (): int => pstcAgentWithCrm(['webhook_enabled' => false])->id],
    'no URL' => [fn (): int => pstcAgentWithCrm(['webhook_url' => ''])->id],
    'no secret' => [fn (): int => pstcAgentWithCrm(['webhook_secret' => null])->id],
    'no CRM settings' => [fn (): int => Agent::factory()->create()->id],
    'an agent that is gone' => [fn (): int => 999_999_999],
]);

it('throws on an unsafe URL or a failed delivery, and counts a spent delivery in failed()', function (string $url, Closure $arrange, string $exception, int $sent): void {
    $agent = pstcAgentWithCrm(['webhook_url' => $url]);
    $arrange();

    expect(fn () => app(PushSubmissionToCrm::class)->handle(pstcEvent($agent->id)))->toThrow($exception);

    Http::assertSentCount($sent);
    app(PushSubmissionToCrm::class)->failed(pstcEvent($agent->id), new RuntimeException('down'));

    expect($agent->fresh()->settings('leads')->get('crm')['webhook_failures'])->toBe(1);
})->with([
    'an unsafe URL, checked at send time outside testing' => ['http://169.254.169.254/latest/meta-data', function (): void {
        app()['env'] = 'production';
        Http::fake();
    }, UnsafeUrlException::class, 0],
    'a failed delivery' => [PSTC_URL, fn () => Http::fake([PSTC_URL => Http::response('', 500)]), RequestException::class, 1],
]);
