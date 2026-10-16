<?php

declare(strict_types=1);

namespace App\Admin\Settings\Actions;

use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Removes a server of your own (Settings → AI providers): each of its models
 * is removed as one model would be ({@see RemoveLlmModelAction}), moving
 * their agents to the replacement, and the server is turned off. Rows stay,
 * so past messages still say which model wrote them.
 */
final readonly class RemoveCustomLlmServerAction
{
    public function __construct(
        private RemoveLlmModelAction $removeModel,
    ) {}

    public function execute(LlmProvider $server, ?Llm $replacement = null): void
    {
        if ($server->kind !== LlmProviderKind::Custom) {
            throw new InvalidArgumentException('Only a server of your own can be removed here.');
        }

        if ($replacement instanceof Llm && $replacement->llm_provider_id === $server->id) {
            throw new InvalidArgumentException('Pick a model of another provider.');
        }

        DB::transaction(function () use ($server, $replacement): void {
            $server->llms()->where('is_enabled', true)->each(fn (Llm $model) => $this->removeModel->execute($model, $replacement));

            $server->update(['is_enabled' => false]);
        });
    }
}
