<?php

namespace Domain\Form\Data;

use Carbon\CarbonInterface;
use Domain\Form\Enums\FormType;

/**
 * A form instance issued into a conversation: everything the chat needs to
 * draw it (FormKit schema, labels, messages) plus the instance id the
 * visitor's answers are later submitted against.
 */
final readonly class InlineFormData
{
    /**
     * @param  list<array<string, mixed>>  $schema  The FormKit schema the chat renders.
     */
    public function __construct(
        public string $formInstanceId,
        public string $definitionSlug,
        public FormType $formType,
        public string $title,
        public ?string $subtitle,
        public array $schema,
        public string $submitLabel,
        public string $successMessage,
        public string $status,
        public ?CarbonInterface $expiresAt,
    ) {}
}
