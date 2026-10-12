<?php

namespace App\Mcp\Tools\Analytics;

use App\Identity\Workspace\Models\Workspace;
use App\Mcp\McpCallerContext;
use Domain\Analytics\Data\Account\AnalyticsFilterData;
use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Enums\AnalyticsPeriod;
use Domain\Analytics\Services\AnalyticsScopeResolver;
use Domain\Analytics\Services\Period;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * Shared base for the internal Account-Analytics tools. Each tool answers a
 * cluster of "numbers about my account" questions for the workspace OWNER,
 * from a read model in Domain\Analytics\Queries\Account.
 *
 * The owner-facing analytics chat signs every call with the workspace's id;
 * the middleware resolves it into {@see McpCallerContext::$workspace}. These
 * tools are never attached to an agent and never see a visitor.
 *
 * The base validates the shared `period` / dates / `agent` arguments, refuses
 * a call without a workspace, resolves the scope, and hands it to
 * {@see answer()}, where a tool maps the read model to its text summary and
 * structured result.
 */
abstract class AnalyticsTool extends Tool
{
    /**
     * Answer for an already resolved scope.
     *
     * @param  array<string, mixed>  $input  The validated tool arguments.
     */
    abstract protected function answer(AnalyticsScopeData $scope, array $input): Response|ResponseFactory;

    public function handle(Request $request, McpCallerContext $caller, AnalyticsScopeResolver $scopes): Response|ResponseFactory
    {
        $input = $request->validate($this->rules());

        if (! $caller->workspace instanceof Workspace) {
            return Response::error('No workspace context for this analytics request.');
        }

        return $this->answer($scopes->resolve($caller->workspace, $this->filter($input)), $input);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->commonSchema($schema);
    }

    /**
     * Validation rules; tools with extra arguments add theirs to these.
     *
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'period' => ['nullable', 'string', 'in:'.implode(',', AnalyticsPeriod::values())],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'agent' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function filter(array $input): AnalyticsFilterData
    {
        return new AnalyticsFilterData(
            period: AnalyticsPeriod::tryFrom((string) ($input['period'] ?? '')),
            startDate: $input['start_date'] ?? null,
            endDate: $input['end_date'] ?? null,
            agentExternalId: ! empty($input['agent']) ? (string) $input['agent'] : null,
        );
    }

    /**
     * Shared schema fragment (period + optional explicit dates + agent).
     *
     * @return array<string, mixed>
     */
    protected function commonSchema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()
                ->enum(AnalyticsPeriod::values())
                ->description('Time window. Defaults to "this_month". Use "last_month" for the one before, or a rolling window like "last_7_days"/"last_30_days".')
                ->default(AnalyticsPeriod::ThisMonth->value),
            'start_date' => $schema->string()
                ->description('Optional explicit start date (YYYY-MM-DD). When given together with end_date it overrides `period`.'),
            'end_date' => $schema->string()
                ->description('Optional explicit end date (YYYY-MM-DD).'),
            'agent' => $schema->string()
                ->description('Optional agent id to narrow the numbers to a single agent. Omit for the whole account (all agents).'),
        ];
    }

    /**
     * The window as reported back to the model.
     *
     * @return array{key: string, label: string, start: string, end: string}
     */
    protected function periodPayload(Period $period): array
    {
        return [
            'key' => $period->key(),
            'label' => $period->label,
            'start' => $period->start->toDateString(),
            'end' => $period->end->toDateString(),
        ];
    }
}
