<?php

namespace Domain\Form\Data;

use Domain\Form\Models\FormDefinition;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Form definition — serves as both the page resource (fromModel) and the
 * save input (built by SaveFormDefinitionRequest::toData; external_id/slug null).
 */
#[TypeScript]
class FormDefinitionData extends Data
{
    public function __construct(
        public ?string $external_id,
        public ?string $slug,
        public string $name,
        // Visitor-facing blurb shown under the title in the rendered form.
        public ?string $subtitle,
        public string $form_type,
        /** @var array<int, array<string, mixed>> */
        public array $schema,
        /** @var array<string, string>|null */
        public ?array $lead_field_map,
        public ?string $success_message,
        public ?string $agent_instructions,
        public bool $is_active,
        // Number of submitted entries — surfaced on the Forms list. Defaulted so
        // the save-input construction (toData) and tests don't have to pass it;
        // fromModel fills it from a withCount on the index query.
        public int $submissions_count = 0,
    ) {}

    public static function fromModel(FormDefinition $form): self
    {
        return new self(
            external_id: $form->external_id,
            slug: $form->slug,
            name: $form->name,
            subtitle: $form->subtitle,
            form_type: $form->form_type->value,
            schema: $form->schema,
            lead_field_map: $form->lead_field_map,
            success_message: $form->success_message,
            agent_instructions: $form->agent_instructions,
            is_active: $form->is_active,
            submissions_count: (int) ($form->submissions_count ?? 0),
        );
    }
}
