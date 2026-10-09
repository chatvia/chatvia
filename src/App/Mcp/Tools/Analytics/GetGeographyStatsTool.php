<?php

namespace App\Mcp\Tools\Analytics;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\Audience\CountryShareData;
use Domain\Analytics\Data\Account\LabelCountData;
use Domain\Analytics\Queries\Account\Audience\GeographyStatsQuery;
use Domain\Analytics\Services\Chart\AnalyticsChart;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * get-geography-stats: top countries and the device / browser / OS mix.
 */
#[Description(<<<'TXT'
Where visitors come from and what they use, for a period: the top countries by
conversations, plus a breakdown of device types, browsers, and operating
systems. Answers "top countries?", "what devices do people chat from?", "mostly
mobile or desktop?". Country counts work well as a map or bar chart.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetGeographyStatsTool extends AnalyticsTool
{
    private const int DEFAULT_LIMIT = 10;

    public function __construct(private readonly GeographyStatsQuery $geography) {}

    protected function answer(AnalyticsScopeData $scope, array $input): Response|ResponseFactory
    {
        $data = $this->geography->get($scope, (int) ($input['limit'] ?? self::DEFAULT_LIMIT));
        $period = $scope->period;

        $countries = array_map(static fn (CountryShareData $country): array => [
            'code' => $country->code,
            'name' => $country->name,
            'conversations' => $country->conversations,
            'pct' => $country->pct,
        ], $data->countries);

        $summary = $countries === []
            ? sprintf('No location data %s yet.', $period->label)
            : sprintf(
                'Top country %s is %s (%s conversations, %s%%).',
                $period->label,
                $countries[0]['name'],
                number_format($countries[0]['conversations']),
                $countries[0]['pct'],
            );

        $structured = [
            'period' => $this->periodPayload($period),
            'countries' => $countries,
            'devices' => $this->counts($data->devices),
            'browsers' => $this->counts($data->browsers),
            'operating_systems' => $this->counts($data->operatingSystems),
        ];

        if (count($countries) > 1) {
            $structured['echarts_option'] = AnalyticsChart::bar(
                array_column($countries, 'name'),
                [['name' => 'Conversations', 'data' => array_column($countries, 'conversations')]],
                'Conversations by country — '.$period->label,
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
                ->description('How many top countries to return (1–50). Defaults to 10.')
                ->default(self::DEFAULT_LIMIT),
        ];
    }

    /**
     * @param  list<LabelCountData>  $counts
     * @return list<array{label: string, count: int}>
     */
    private function counts(array $counts): array
    {
        return array_map(static fn (LabelCountData $count): array => ['label' => $count->label, 'count' => $count->count], $counts);
    }
}
