<?php

declare(strict_types=1);

namespace Domain\Llm\Data\Catalog;

use Domain\Llm\Enums\ModelListStatus;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * A provider's chat models as its list gave them, or why there are none. A
 * list that failed for another reason than the key still lets an admin type
 * a model's name.
 */
final class ModelListData extends Data
{
    /**
     * @param  list<RemoteModelData>  $models
     */
    public function __construct(
        public ModelListStatus $status,
        #[DataCollectionOf(RemoteModelData::class)]
        public array $models = [],
        public ?int $httpStatus = null,
    ) {}

    /** Whether the provider accepted the key (or needs none). */
    public function keyAccepted(): bool
    {
        return ! collect([ModelListStatus::KeyRefused, ModelListStatus::Unreachable])->containsStrict($this->status);
    }

    /** Why there is no list, in plain words, or null when there is one. */
    public function message(string $label, string $address): ?string
    {
        return match ($this->status) {
            ModelListStatus::Listed => null,
            ModelListStatus::KeyRefused => "{$label} refused the API key. Check that you copied all of it.",
            ModelListStatus::Unreachable => "{$address} could not be reached. Check the address.",
            ModelListStatus::Failed => "The model list could not be loaded ({$this->httpStatus}). Type the model's name instead.",
            ModelListStatus::Empty => "{$label} lists no chat models. Type the model's name instead.",
        };
    }
}
