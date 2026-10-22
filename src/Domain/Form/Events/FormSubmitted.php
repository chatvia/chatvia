<?php

declare(strict_types=1);

namespace Domain\Form\Events;

use Domain\Form\Data\CapturedSubmissionData;
use Domain\Form\Listeners\PushSubmissionToCrm;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

/**
 * A form submission was captured — from an inline form OR the conversational
 * capture path. The submission travels as {@see CapturedSubmissionData}, so
 * the queued CRM webhook listener ({@see PushSubmissionToCrm}) never has to
 * re-read the row. `deliveryId` is fixed when the event is made and travels
 * with the queued listener, so every retry of the webhook carries the same
 * id and the receiver can drop a repeat.
 */
final readonly class FormSubmitted
{
    use Dispatchable;

    public string $deliveryId;

    public function __construct(
        public CapturedSubmissionData $submission,
    ) {
        $this->deliveryId = Str::uuid7()->toString();
    }
}
