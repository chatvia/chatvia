<?php

namespace App\Mcp\Tools\Analytics;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\LabelCountData;
use Domain\Analytics\Queries\Account\Audience\TopTopicsQuery;
use Domain\Analytics\Services\Chart\AnalyticsChart;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * get-top-topics: what visitors ask about most.
 */
#[Description(<<<'TXT'
The topics visitors talk about most, for a period — each topic ranked by the
number of conversations it appears in. Answers "what are people asking about?",
"top topics this cycle?", "what's trending?". Great input for a bar chart of
demand by topic.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetTopTopicsTool extends AnalyticsTool
{
    private const int DEFAULT_LIMIT = 10;

    public function __construct(private readonly TopTopicsQuery $topics) {}

    protected function answer(AnalyticsScopeData $scope, array $input): Response|ResponseFactory
    {
        $topics = array_map(
            static fn (LabelCountData $topic): array => ['topic' => $topic->label, 'conversations' => $topic->count],
            $this->topics->get($scope, (int) ($input['limit'] ?? self::DEFAULT_LIMIT)),
        );
        $period = $scope->period;

        $summary = $topics === []
            ? sprintf('No topics recorded %s yet.', $period->label)
            : sprintf(
                'Top topic %s is "%s" (%s conversations).',
                $period->label,
                $topics[0]['topic'],
                number_format($topics[0]['conversations']),
            );

        $structured = [
            'period' => $this->periodPayload($period),
            'topics' => $topics,
        ];

        if (count($topics) > 1) {
            $structured['echarts_option'] = AnalyticsChart::bar(
                array_column($topics, 'topic'),
                [['name' => 'Conversations', 'data' => array_column($topics, 'conversations')]],
                'Top topics — '.$period->label,
                horizontal: true,
            );
        }

        return Response::make(Response::text($summary))->withStructuredContent($structured);
    }

    protected function rules(): array
    {
        return [
            ...parent::rules(),
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->commonSchema($schema),
            'limit' => $schema->integer()
                ->description('How many top topics to return (1–50). Defaults to 10.')
                ->default(self::DEFAULT_LIMIT),
        ];
    }
}
