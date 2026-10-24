<?php

declare(strict_types=1);

namespace Tests\Support\Conversation;

use Domain\Conversation\Services\ChatContext;
use Domain\Guardrail\Data\GuardResultData;
use Domain\Guardrail\Services\GuardrailRecorder;

/** A {@see GuardrailRecorder} that logs nothing and keeps every sample it was given. */
final class RecordingGuardrailRecorder extends GuardrailRecorder
{
    /** @var list<string> */
    public array $samples = [];

    public function __construct()
    {
        // No parent constructor: nothing is logged.
    }

    public function record(ChatContext $context, string $sample, GuardResultData $result): void
    {
        $this->samples[] = $sample;
    }
}
