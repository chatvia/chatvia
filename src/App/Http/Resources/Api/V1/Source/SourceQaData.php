<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Source;

use Carbon\CarbonInterface;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Spatie\LaravelData\Data;

/**
 * A curated question-and-answer entry as the API returns it. Q&A entries are
 * matched directly (no training pipeline), so unlike the other sources they
 * carry no `status`.
 */
class SourceQaData extends Data
{
    public function __construct(
        /**
         * The entry's ID. Use it to delete the entry.
         */
        public string $id,
        /**
         * The entry's name.
         */
        public string $title,
        /**
         * The phrasings of the question.
         *
         * @var list<string>
         */
        public array $questions,
        /**
         * The answer the agent gives word for word.
         */
        public string $answer,
        /**
         * When the entry was added.
         */
        public ?CarbonInterface $created_at,
    ) {}

    public static function fromModel(AgentQuestionAnswer $qa): self
    {
        return new self(
            id: $qa->external_id,
            title: $qa->title,
            questions: array_values(array_filter(explode("\n", $qa->questions))),
            answer: $qa->answer,
            created_at: $qa->created_at,
        );
    }
}
