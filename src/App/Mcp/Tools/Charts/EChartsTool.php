<?php

namespace App\Mcp\Tools\Charts;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * Shared shape of every chart tool on the Charts server. A chart tool takes a
 * clean, typed JSON input plus a one-line `summary`, turns it into an Apache
 * ECharts `option`, and returns the summary as text with the option as
 * `structuredContent.echarts_option` — the chat UI renders that as a chart.
 *
 * The base owns what every chart has in common: the optional `title` (shown
 * centred on top), the optional axis-name fields, the required `summary`, the
 * order they appear in the schema and the validation rules, and the result
 * envelope. A subclass supplies only its own fields and the option it builds.
 *
 * The input schema is a contract with the LLM, so each subclass keeps its
 * exact wording through {@see TITLE_HINT}, {@see SUMMARY_HINT} and
 * {@see AXIS_LABELS}. Laravel MCP reads tool annotations off the concrete
 * class only, so #[IsReadOnly]/#[IsIdempotent] stay on each subclass.
 */
abstract class EChartsTool extends Tool
{
    /** Schema hint for the optional chart title. */
    protected const string TITLE_HINT = 'Optional chart title.';

    /** Schema hint for the required one-sentence summary. */
    protected const string SUMMARY_HINT = 'One sentence describing what the chart shows.';

    /**
     * Optional axis-name fields this chart accepts, as field => schema hint.
     * They sit between `title` and `summary` in the schema and the rules.
     *
     * @var array<string, string>
     */
    protected const array AXIS_LABELS = [];

    /**
     * Validation rules for the chart's own fields.
     *
     * @return array<string, list<string>>
     */
    abstract protected function chartRules(): array;

    /**
     * JSON schema for the chart's own fields.
     *
     * @return array<string, mixed>
     */
    abstract protected function chartSchema(JsonSchema $schema): array;

    /**
     * Build the ECharts option (without the title, which the base adds).
     *
     * @param  array<string, mixed>  $input  The validated tool arguments.
     * @return array<string, mixed>
     *
     * @throws InvalidChartInputException when the arguments contradict each other.
     */
    abstract protected function option(array $input): array;

    public function handle(Request $request): Response|ResponseFactory
    {
        $input = $request->validate([
            ...$this->chartRules(),
            'title' => ['nullable', 'string', 'max:120'],
            ...array_map(static fn (): array => ['nullable', 'string', 'max:60'], static::AXIS_LABELS),
            'summary' => ['required', 'string', 'max:500'],
        ]);

        try {
            $option = $this->option($input);
        } catch (InvalidChartInputException $e) {
            return Response::error($e->getMessage());
        }

        if ($this->hasTitle($input)) {
            $option['title'] = ['text' => $input['title'], 'left' => 'center'];
        }

        return Response::make(Response::text($input['summary']))
            ->withStructuredContent(['echarts_option' => $option]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->chartSchema($schema),
            'title' => $schema->string()->description(static::TITLE_HINT),
            ...array_map(static fn (string $hint): mixed => $schema->string()->description($hint), static::AXIS_LABELS),
            'summary' => $schema->string()
                ->description(static::SUMMARY_HINT)
                ->required(),
        ];
    }

    /**
     * Charts leave more room at the top when a title is shown.
     *
     * @param  array<string, mixed>  $input
     */
    protected function hasTitle(array $input): bool
    {
        return ! empty($input['title']);
    }

    /**
     * Name an axis when the caller supplied a label for it.
     *
     * @param  array<string, mixed>  $axis
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function withAxisName(array $axis, array $input, string $labelField): array
    {
        if (! empty($input[$labelField])) {
            $axis['name'] = $input[$labelField];
        }

        return $axis;
    }
}
