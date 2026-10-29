<?php

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Data\GuardrailSettingsData;
use Domain\Guardrail\Enums\GuardMode;
use Domain\Guardrail\Enums\GuardOutcome;
use Domain\Guardrail\Enums\GuardSensitivity;
use Domain\Guardrail\Enums\GuardType;
use Domain\Guardrail\Enums\TopicResponse;
use Domain\Guardrail\Models\GuardrailEvent;
use Domain\Llm\Operations\Translate\TextTranslator;
use Inertia\Testing\AssertableInertia;

/**
 * The Guardrails page: the form saves topics with their response, custom
 * patterns and each guard's mode and sensitivity (answers stay checked when
 * the switch is left out), and refuses a broken pattern; the Activity log
 * filters by guard and outcome, and names who is behind each abusive trigger.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->owner = $this->agent->workspace->owner;
    $this->mock(TextTranslator::class)->shouldReceive('translate')->andReturn([]);
});

it('saves the guardrail settings and keeps checking answers when the form leaves the switch out', function (): void {
    $form = agcForm();
    unset($form['check_answers']);

    $this->actingAs($this->owner)
        ->from(route('agents.guardrails', $this->agent->external_id))
        ->post(route('agents.guardrails.update', $this->agent->external_id), $form)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $settings = GuardrailSettingsData::fromAgent($this->agent->fresh());

    expect($settings->blocked_topics[0]->name)->toBe('Complaints')
        ->and($settings->blocked_topics[0]->action)->toBe(TopicResponse::Handoff)
        ->and($settings->blocked_topics[0]->examples)->toBe(['I want to complain'])
        ->and($settings->custom_patterns[0]->type())->toBe('custom_customer_number')
        ->and($settings->pii_actions)->toBe(['credit_card' => 'block'])
        ->and($settings->injection_mode)->toBe(GuardMode::Monitor)
        ->and($settings->injection_sensitivity)->toBe(GuardSensitivity::Medium)
        ->and($settings->toxicity_sensitivity)->toBe(GuardSensitivity::Low)
        ->and($settings->check_answers)->toBeTrue()
        ->and($settings->check_before_showing)->toBeTrue()
        ->and($settings->alert_owner)->toBeTrue();
});

it('refuses a custom pattern that does not compile or matches empty text', function (string $pattern): void {
    $this->actingAs($this->owner)
        ->from(route('agents.guardrails', $this->agent->external_id))
        ->post(route('agents.guardrails.update', $this->agent->external_id), agcForm([
            'custom_patterns' => [['name' => 'Broken', 'pattern' => $pattern, 'action' => 'mask']],
        ]))
        ->assertSessionHasErrors('custom_patterns.0.pattern');
})->with(['unclosed group' => ['K-(\d+'], 'matches empty text' => ['\d*']]);

it('filters the activity log by guard and outcome', function (): void {
    GuardrailEvent::factory()->forAgent($this->agent)->create(['guard_type' => GuardType::Pii, 'outcome' => GuardOutcome::Redacted]);
    GuardrailEvent::factory()->forAgent($this->agent)->create(['guard_type' => GuardType::Toxicity, 'outcome' => GuardOutcome::Skipped]);

    $this->actingAs($this->owner)
        ->get(route('agents.guardrails', ['agent' => $this->agent->external_id, 'outcome' => 'skipped']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('filters.outcome', 'skipped')
            ->where('triggers.total', 1)
            ->where('triggers.data.0.guard_type', 'toxicity'));
});

it('shows who is behind each abusive trigger and how often they tried', function (): void {
    GuardrailEvent::factory()->forAgent($this->agent)->fromVisitor('visitor-1')->count(2)->create();
    GuardrailEvent::factory()->forAgent($this->agent)->fromVisitor('visitor-1')->create(['guard_type' => GuardType::Pii, 'outcome' => GuardOutcome::Redacted]);

    $this->actingAs($this->owner)
        ->get(route('agents.guardrails', $this->agent->external_id))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('triggers.data.0.offender', null)
            ->where('triggers.data.1.offender.kind', 'visitor')
            ->where('triggers.data.1.offender.recent_attempts', 2)
            ->where('triggers.data.1.offender.blocked', false)
            ->where('triggers.data.1.visitor_key', hash('sha256', 'visitor-1')));
});

/**
 * A complete Guardrails form, with any field changed.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function agcForm(array $overrides = []): array
{
    return array_merge([
        'enabled' => true,
        'filters' => ['email', 'credit_card'],
        'pii_actions' => ['credit_card' => 'block'],
        'custom_patterns' => [['name' => 'Customer number', 'pattern' => 'K-\d{6}', 'action' => 'mask']],
        'allowlist' => ['support@acme.com'],
        'injection' => true,
        'injection_mode' => 'monitor',
        'injection_sensitivity' => 'medium',
        'toxicity' => true,
        'toxicity_mode' => 'block',
        'toxicity_sensitivity' => 'low',
        'topics_mode' => 'block',
        'blocked_topics' => [[
            'name' => 'Complaints',
            'description' => 'Unhappy customers',
            'examples' => ['I want to complain'],
            'action' => 'handoff',
            'reply' => null,
        ]],
        'allowed_scope' => '',
        'refusal_message' => '',
        'check_answers' => false,
        'check_before_showing' => true,
        'alert_owner' => true,
    ], $overrides);
}
