<?php

namespace Domain\Guardrail\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What "Test a message" answers the Guardrails page: what the visitor would
 * get and which guards fired. Nothing is saved or logged.
 */
#[TypeScript]
class GuardrailTestResultData extends Data
{
    public function __construct(
        public bool $blocked,
        public string $text,
        public ?string $reply,
        public bool $handoff,
        public ?string $language,
        /** @var DataCollection<int, GuardrailTestVerdictData> */
        #[DataCollectionOf(GuardrailTestVerdictData::class)]
        public DataCollection $verdicts,
    ) {}

    public static function fromResult(GuardResultData $result): self
    {
        return new self(
            blocked: $result->blocked,
            text: $result->text,
            reply: $result->handoffMessage ?? $result->refusalMessage,
            handoff: $result->handoffMessage !== null,
            language: $result->language,
            verdicts: new DataCollection(
                GuardrailTestVerdictData::class,
                array_map(GuardrailTestVerdictData::fromVerdict(...), $result->verdicts),
            ),
        );
    }
}
