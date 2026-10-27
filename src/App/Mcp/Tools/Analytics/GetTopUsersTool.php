<?php

namespace App\Mcp\Tools\Analytics;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\Audience\TopUserData;
use Domain\Analytics\Queries\Account\Audience\TopUsersQuery;
use Domain\Analytics\Services\Chart\AnalyticsChart;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Number;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * get-top-users: the most active identified end-users.
 */
#[Description(<<<'TXT'
The most active end-users (identified chatters) for a period, ranked by how many
messages they sent, plus the count of unique identified users and how many new
visitors arrived. Answers "who is my most active user?", "top 10 users by
messages", "how many unique / new visitors?". Anonymous widget visitors with no
account are not individually named but are reflected in the new-visitor count.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetTopUsersTool extends AnalyticsTool
{
    private const int DEFAULT_LIMIT = 5;

    public function __construct(private readonly TopUsersQuery $users) {}

    protected function answer(AnalyticsScopeData $scope, array $input): Response|ResponseFactory
    {
        $data = $this->users->get($scope, (int) ($input['limit'] ?? self::DEFAULT_LIMIT));
        $period = $scope->period;

        $top = array_map(static fn (TopUserData $user): array => [
            'name' => $user->name,
            'email' => $user->email,
            'messages' => $user->messages,
        ], $data->topUsers);

        $summary = $top === []
            ? sprintf('No identified users sent messages %s yet.', $period->label)
            : sprintf(
                'Most active %s: %s with %s messages. %s identified users in total, %s new visitors.',
                $period->label,
                $top[0]['name'],
                Number::format($top[0]['messages']),
                Number::format($data->uniqueIdentifiedUsers),
                Number::format($data->newVisitors),
            );

        $structured = [
            'period' => $this->periodPayload($period),
            'top_users' => $top,
            'unique_identified_users' => $data->uniqueIdentifiedUsers,
            'new_visitors' => $data->newVisitors,
        ];

        if (count($top) > 1) {
            $structured['echarts_option'] = AnalyticsChart::bar(
                array_column($top, 'name'),
                [['name' => 'Messages', 'data' => array_column($top, 'messages')]],
                "Most active users — {$period->label}",
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
                ->description('How many top users to return (1–50). Defaults to 5.')
                ->default(self::DEFAULT_LIMIT),
        ];
    }
}
