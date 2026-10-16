<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Form;

use Carbon\CarbonInterface;
use Domain\Form\Models\FormSubmission;
use Spatie\LaravelData\Data;

/**
 * One captured submission (lead) of a form over the public API. `values`
 * holds the visitor's answers as submitted, keyed by the form's field keys —
 * they can include personal data.
 */
class SubmissionData extends Data
{
    public function __construct(
        /**
         * The submission's ID.
         */
        public string $id,
        /**
         * The visitor's answers, keyed by field key.
         *
         * @var array<string, mixed>
         */
        public array $values,
        /**
         * When the visitor submitted the form.
         */
        public ?CarbonInterface $submitted_at,
        /**
         * The conversation the form was submitted in, when there is one.
         */
        public ?string $conversation_id,
        /**
         * When the submission row was created.
         */
        public ?CarbonInterface $created_at,
    ) {}

    public static function fromModel(FormSubmission $submission): self
    {
        return new self(
            id: $submission->external_id,
            values: $submission->values ?? [],
            submitted_at: $submission->submitted_at,
            conversation_id: $submission->relationLoaded('conversation') ? $submission->conversation?->external_id : null,
            created_at: $submission->created_at,
        );
    }
}
