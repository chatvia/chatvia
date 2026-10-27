<?php

declare(strict_types=1);

namespace Tests\Support\Guardrail;

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Data\ClassifierVerdictData;
use Domain\Guardrail\Enums\PiiFilterType;
use Domain\Guardrail\Services\Detectors\InjectionPatternDetector;
use Domain\Guardrail\Services\Detectors\PiiDetector;
use Domain\Guardrail\Services\GuardrailClassifier;
use Domain\Guardrail\Services\GuardrailInspector;
use GuzzleHttp\Promise\Create;
use Mockery;

/**
 * What the GuardrailInspector tests build on: an agent whose guardrail and
 * business settings are saved as the Guardrails page saves them (every PII
 * filter of the defaults on, and a fixed refusal), and the service with a
 * classifier, the LLM boundary, that answers with a fixed verdict.
 */
final class GuardedAgent
{
    /**
     * @param  array<string, mixed>  $guardrails  settings over the defaults
     * @param  array<string, mixed>  $business  the agent's contact details
     */
    public static function make(array $guardrails = [], array $business = []): Agent
    {
        $agent = Agent::factory()->create();

        $settings = ['filters' => PiiFilterType::defaults(), 'refusal_message' => 'I can’t help with that.', ...$guardrails];

        foreach ($settings as $key => $value) {
            $agent->setSetting($key, $value, 'guardrails');
        }

        foreach ($business as $key => $value) {
            $agent->setSetting($key, $value, 'business');
        }

        return $agent;
    }

    /** The service, its classifier answering every check with this verdict (no finding by default). */
    public static function service(?ClassifierVerdictData $verdict = null): GuardrailInspector
    {
        $classifier = Mockery::mock(GuardrailClassifier::class);
        $classifier->shouldReceive('classifyAsync')->andReturn(Create::promiseFor($verdict ?? new ClassifierVerdictData));

        return self::serviceWith($classifier);
    }

    /** The service with a classifier the test set up, to check how it is called. */
    public static function serviceWith(GuardrailClassifier $classifier): GuardrailInspector
    {
        return new GuardrailInspector(new PiiDetector, $classifier, new InjectionPatternDetector);
    }

    /** The service with a classifier that must not be called. */
    public static function serviceWithoutClassifier(): GuardrailInspector
    {
        $classifier = Mockery::mock(GuardrailClassifier::class);
        $classifier->shouldNotReceive('classifyAsync');

        return self::serviceWith($classifier);
    }
}
