<?php

declare(strict_types=1);

namespace App\Admin\Settings\Actions;

use App\Admin\Settings\AiSettings;
use App\Admin\Settings\TaskModelPreset;
use Domain\Llm\Actions\Catalog\AddPresetLlmModelAction;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Illuminate\Support\Facades\DB;

/**
 * "Use the recommended models" (Settings → AI models → Models for app
 * tasks): adds the provider's small model for quick tasks, and its model
 * that reads images, to the catalog and gives the tasks the models of the
 * preset ({@see TaskModelPreset}): the small one for app tasks, the chat
 * model for the tasks that need a large one, the one that reads images for
 * image descriptions. Without that one, image descriptions are turned off
 * (Settings → AI models → Documents). A model picked for a single task before is
 * cleared.
 */
final readonly class ApplyRecommendedTaskModelsAction
{
    public function __construct(
        private AddPresetLlmModelAction $addModel,
        private AiSettings $ai,
    ) {}

    /**
     * @return Llm the model for quick tasks
     */
    public function execute(LlmProvider $provider, RemoteModelData $small, Llm $chat, ?RemoteModelData $vision = null): Llm
    {
        return DB::transaction(function () use ($provider, $small, $chat, $vision): Llm {
            $model = $this->addModel->execute($provider, $small);
            $reader = $vision instanceof RemoteModelData ? $this->addModel->execute($provider, $vision) : null;

            foreach (TaskModelPreset::values($chat, $model, $reader) as $property => $value) {
                $this->ai->{$property} = $value;
            }

            if (! $reader instanceof Llm) {
                $this->ai->describe_images = false;
            }

            $this->ai->save();

            return $model;
        });
    }
}
