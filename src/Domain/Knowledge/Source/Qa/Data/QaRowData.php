<?php

namespace Domain\Knowledge\Source\Qa\Data;

use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Illuminate\Support\Str;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One Q&A entry as the dashboard's Q&A endpoints return it. A Q&A entry needs
 * no ingestion, so it always reports the ingested status.
 */
#[TypeScript]
class QaRowData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        /** @var DataCollection<int, QaQuestionItemData> */
        #[DataCollectionOf(QaQuestionItemData::class)]
        public DataCollection $questions,
        public string $answer,
        public string $status,
        public ?string $created_at,
        public ?string $updated_at,
    ) {}

    public static function fromModel(AgentQuestionAnswer $qa): self
    {
        $questions = Str::of($qa->questions)->explode("\n")
            ->map(fn (string $question): string => Str::trim($question))
            ->filter()
            ->values()
            ->map(fn (string $question): QaQuestionItemData => QaQuestionItemData::fromQuestion($question))
            ->all();

        return new self(
            id: $qa->id,
            title: $qa->title,
            questions: new DataCollection(QaQuestionItemData::class, $questions),
            answer: $qa->answer,
            status: SourceStatus::Completed->value,
            created_at: $qa->created_at?->toIso8601String(),
            updated_at: $qa->updated_at?->toIso8601String(),
        );
    }
}
