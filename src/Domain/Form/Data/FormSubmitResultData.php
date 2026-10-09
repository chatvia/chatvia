<?php

declare(strict_types=1);

namespace Domain\Form\Data;

use Domain\Form\Enums\FormSubmissionStatus;
use Spatie\LaravelData\Data;

/**
 * What the chat shows after a visitor sends a form: the new status, the
 * form's thank-you message and the answers that were kept. Serialised as the
 * JSON body of the submit endpoints.
 */
class FormSubmitResultData extends Data
{
    /**
     * @param  array<string, mixed>  $submitted_values
     */
    public function __construct(
        public FormSubmissionStatus $status,
        public string $success_message,
        public array $submitted_values,
    ) {}
}
