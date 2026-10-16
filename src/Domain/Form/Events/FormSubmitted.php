<?php

declare(strict_types=1);

namespace Domain\Form\Events;

use Domain\Form\Data\CapturedSubmissionData;
use Domain\Form\Listeners\PushSubmissionToCrm;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A form submission was captured — from an inline form OR the conversational
 * capture path. The submission travels as {@see CapturedSubmissionData}, so
 * the queued CRM webhook listener ({@see PushSubmissionToCrm}) never has to
 * re-read the row.
 */
final readonly class FormSubmitted
{
    use Dispatchable;

    public function __construct(
        public CapturedSubmissionData $submission,
    ) {}
}
