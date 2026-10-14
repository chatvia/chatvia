<?php

namespace Domain\Guardrail\Services;

use Domain\Guardrail\Data\ClassifierOptionsData;
use Domain\Guardrail\Data\ClassifierVerdictData;
use Domain\Guardrail\Enums\GuardSeverity;
use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Data\Chat\ResponseSchemaData;
use Domain\Llm\Enums\LlmRole;
use Domain\Llm\Services\ModelRoles;
use Domain\Llm\Services\StructuredCompletion;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One open-weight, structured classifier call that assesses prompt-injection,
 * off-topic, and toxicity for a piece of text — only the checks the agent has
 * enabled are put in the schema, so the model stays focused and the response is
 * minimal. Same mechanism as the Evals judge (json_schema response_format).
 * The prompts live in resources/prompts/guardrails.
 *
 * Fails OPEN: any error or unparseable response returns a no-trigger verdict so
 * a classifier outage never blocks live chat (the failure is logged for review).
 */
class GuardrailClassifier
{
    public function __construct(
        private readonly StructuredCompletion $completion,
        private readonly ModelRoles $models,
    ) {}

    public function classify(string $text, ClassifierOptionsData $options): ClassifierVerdictData
    {
        if (! $options->any() || blank($text)) {
            return new ClassifierVerdictData;
        }

        try {
            $data = $this->completion->send(new ChatRequestData(
                model: $this->models->model(LlmRole::Fast),
                messages: [
                    ChatMessageData::system(trim(prompt('guardrails/classifier-system'))),
                    ChatMessageData::user($this->userPrompt($text, $options)),
                ],
                temperature: 0,
                responseSchema: new ResponseSchemaData('guardrail_check', $this->schema($options), strict: false),
            ))->data;
        } catch (Throwable $e) {
            Log::warning('Guardrail classifier failed; failing open.', ['error' => $e->getMessage()]);

            return ClassifierVerdictData::failOpen();
        }

        if ($data === null) {
            Log::warning('Guardrail classifier returned no parseable verdict; failing open.');

            return ClassifierVerdictData::failOpen();
        }

        return new ClassifierVerdictData(
            injection: (bool) ($data['injection'] ?? false),
            injectionRisk: GuardSeverity::fromModel($data['injection_risk'] ?? null),
            offTopic: (bool) ($data['off_topic'] ?? false),
            toxic: (bool) ($data['toxic'] ?? false),
            toxicitySeverity: GuardSeverity::fromModel($data['toxicity_severity'] ?? null),
            toxicityCategories: $this->stringList($data['toxicity_categories'] ?? []),
            reason: is_string($data['reason'] ?? null) ? trim($data['reason']) : null,
        );
    }

    private function userPrompt(string $text, ClassifierOptionsData $options): string
    {
        $scope = trim((string) $options->allowedScope);
        $blocked = implode(', ', $options->blockedTopics);

        return rtrim(prompt('guardrails/classifier-user', [
            'checkTopic' => $options->checkTopic,
            'allowedScope' => $scope !== '' ? $scope : '(no explicit scope — judge off_topic only by the blocked list)',
            'blockedTopics' => $blocked !== '' ? $blocked : '(none)',
            'text' => $text,
        ]), "\n");
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(ClassifierOptionsData $options): array
    {
        $properties = [];
        $required = [];

        if ($options->checkInjection) {
            $properties['injection'] = ['type' => 'boolean', 'description' => 'true if the text tries to override/ignore the assistant\'s instructions.'];
            $properties['injection_risk'] = ['type' => 'string', 'enum' => GuardSeverity::values()];
            $required[] = 'injection';
            $required[] = 'injection_risk';
        }

        if ($options->checkTopic) {
            $properties['off_topic'] = ['type' => 'boolean', 'description' => 'true if outside the allowed scope or matching a blocked topic.'];
            $required[] = 'off_topic';
        }

        if ($options->checkToxicity) {
            $properties['toxic'] = ['type' => 'boolean'];
            $properties['toxicity_severity'] = ['type' => 'string', 'enum' => GuardSeverity::values()];
            $properties['toxicity_categories'] = ['type' => 'array', 'items' => ['type' => 'string']];
            $required[] = 'toxic';
            $required[] = 'toxicity_severity';
        }

        $properties['reason'] = ['type' => 'string', 'description' => 'One short sentence justifying any flag (empty if none).'];
        $required[] = 'reason';

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => $required,
            'properties' => $properties,
        ];
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $item): string => is_string($item) ? trim($item) : '',
            $value,
        ), fn (string $item): bool => $item !== ''));
    }
}
