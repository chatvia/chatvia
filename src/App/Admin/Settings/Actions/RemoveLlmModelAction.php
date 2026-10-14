<?php

declare(strict_types=1);

namespace App\Admin\Settings\Actions;

use App\Admin\Settings\AiSettings;
use Domain\Agent\Actions\Settings\ReplaceAgentModelAction;
use Domain\Agent\Models\Agent;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Takes a model off the list agents can use (Settings → AI models). Agents
 * that answer with it move to the replacement, and so does the default for
 * new agents; a job (Fast, Smart, Vision) that used it goes back to the
 * default. The model's row is turned off, never deleted, so past messages
 * still say which model wrote them, and adding it again turns it back on.
 */
final readonly class RemoveLlmModelAction
{
    public function __construct(
        private ReplaceAgentModelAction $replaceAgentModel,
        private AiSettings $ai,
    ) {}

    public function execute(Llm $model, ?Llm $replacement = null): void
    {
        if ($replacement?->is($model)) {
            throw new InvalidArgumentException('A model cannot replace itself.');
        }

        if (! $replacement instanceof Llm && Agent::query()->where('llm_id', $model->id)->exists()) {
            throw new InvalidArgumentException("Agents answer with {$model->descriptive_name}: pick a model for them first.");
        }

        DB::transaction(function () use ($model, $replacement): void {
            if ($replacement instanceof Llm) {
                $this->replaceAgentModel->execute($model, $replacement);
            }

            $model->update(['is_enabled' => false]);

            if ($this->ai->default_agent_model === $model->external_name) {
                $this->ai->default_agent_model = $replacement?->external_name;
            }

            foreach (['fast_model', 'smart_model', 'vision_model'] as $role) {
                if ($this->ai->{$role} === $model->external_name) {
                    $this->ai->{$role} = null;
                }
            }

            $this->ai->save();
        });
    }
}
