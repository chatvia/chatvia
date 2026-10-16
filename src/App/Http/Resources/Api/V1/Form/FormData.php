<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Form;

use Carbon\CarbonInterface;
use Domain\Form\Enums\FormType;
use Domain\Form\Models\FormDefinition;
use Spatie\LaravelData\Data;

/**
 * One of an agent's inline forms over the public API: its texts, type, the
 * field schema the chat renders and how many submissions it has captured.
 */
class FormData extends Data
{
    public function __construct(
        /**
         * The form's ID.
         */
        public string $id,
        /**
         * URL-safe identifier derived from the name on create; stable afterwards.
         */
        public string $slug,
        /**
         * The form's title, shown above the rendered form.
         */
        public string $name,
        /**
         * Visitor-facing blurb shown under the title.
         */
        public ?string $subtitle,
        /**
         * What the form captures: `lead`, `booking`, `qualification` or `generic`.
         */
        public FormType $form_type,
        /**
         * The field definitions the chat renders, in order.
         *
         * @var list<array<string, mixed>>
         */
        public array $schema,
        /**
         * Maps field keys to the standard lead fields (name, email, phone…).
         *
         * @var array<string, string>|null
         */
        public ?array $lead_field_map,
        /**
         * Shown to the visitor after a successful submission.
         */
        public ?string $success_message,
        /**
         * Guidance for the agent on when to offer this form.
         */
        public ?string $agent_instructions,
        /**
         * Whether the agent may render the form.
         */
        public bool $is_active,
        /**
         * How many submitted entries the form has captured.
         */
        public int $submissions_count,
        /**
         * When the form was created.
         */
        public ?CarbonInterface $created_at,
    ) {}

    public static function fromModel(FormDefinition $form): self
    {
        return new self(
            id: $form->external_id,
            slug: $form->slug,
            name: $form->name,
            subtitle: $form->subtitle,
            form_type: $form->form_type,
            schema: array_values($form->schema),
            lead_field_map: $form->lead_field_map,
            success_message: $form->success_message,
            agent_instructions: $form->agent_instructions,
            is_active: $form->is_active,
            submissions_count: (int) ($form->submissions_count ?? 0),
            created_at: $form->created_at,
        );
    }
}
