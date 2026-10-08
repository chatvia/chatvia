<?php

namespace Domain\Form\Data;

use Domain\Form\Models\FormSubmission;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One submission for the Forms → Submissions view, shown to the authorized
 * workspace owner. `values` holds the visitor's answers as submitted, which
 * can include personal data.
 */
#[TypeScript]
class SubmissionData extends Data
{
    public function __construct(
        public string $external_id,
        /** @var array<string, mixed> */
        public array $values,
        public string $status,
        public ?string $submitted_at,
        public string $created_at,
        public ?string $conversation_external_id,
    ) {}

    public static function fromModel(FormSubmission $submission): self
    {
        return new self(
            external_id: $submission->external_id,
            values: $submission->values ?? [],
            status: $submission->status,
            submitted_at: $submission->submitted_at?->toIso8601String(),
            created_at: $submission->created_at->toIso8601String(),
            conversation_external_id: $submission->conversation?->external_id,
        );
    }
}
