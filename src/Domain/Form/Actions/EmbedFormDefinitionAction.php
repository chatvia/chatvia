<?php

declare(strict_types=1);

namespace Domain\Form\Actions;

use Domain\Form\Models\FormDefinition;
use Domain\Form\Services\FormHybridSearch;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Populates `form_definitions.embedding` (dense vector) and
 * `form_definitions.search_text` (what the lexical channel searches) for a
 * single form. Dispatched by the FormDefinition observer on create/update so a
 * form becomes scorable by {@see FormHybridSearch} — the
 * agent picks the right form by the visitor's INTENT rather than its title.
 */
class EmbedFormDefinitionAction
{
    public function __construct(
        private readonly EmbeddingsProviderInterface $embeddings,
    ) {}

    public function execute(int $formId): void
    {
        $form = FormDefinition::query()->find($formId);
        if (! $form) {
            return;
        }

        try {
            // The name and the agent instructions are what the visitor's intent
            // is matched against, so they are the embedded text.
            $text = Str::trim($form->name."\n\n".$form->agent_instructions);
            $form->forceFill([
                'embedding' => $this->embeddings->embed($text),
                'search_text' => $text,
            ])->save();
        } catch (Throwable $e) {
            Log::warning('Form embedding failed', [
                'form_definition_id' => $form->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
