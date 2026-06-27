<?php

namespace Domain\Form\Actions;

use Domain\Form\Models\FormDefinition;
use Domain\Form\Services\FormHybridSearch;
use Domain\Knowledge\Ingestion\Services\Search\Bm25Tokenizer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Populates `form_definitions.embedding` (Qwen3 dense vector) and
 * `form_definitions.embedding_bm25` (BM25 sparse vector via llmlingua2) for a
 * single form. Dispatched by the FormDefinition observer on create/update so a
 * form becomes scorable by {@see FormHybridSearch} — the
 * agent picks the right form by the visitor's INTENT rather than its title.
 */
class EmbedFormDefinitionAction
{
    public function execute(int $formId): void
    {
        $form = FormDefinition::query()->find($formId);
        if (! $form) {
            return;
        }

        try {
            // The name and the agent instructions are what the visitor's intent
            // is matched against, so they are the embedded text.
            $text = trim($form->name."\n\n".(string) $form->agent_instructions);
            $form->forceFill([
                'embedding' => embed($text),
                'embedding_bm25' => Bm25Tokenizer::expression($text),
            ])->save();
        } catch (Throwable $e) {
            Log::warning('Form embedding failed', [
                'form_definition_id' => $form->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
