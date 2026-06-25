<?php

namespace Domain\Form\Services;

use Domain\Agent\Models\Agent;
use Domain\Form\Enums\FormType;
use Domain\Form\Models\FormDefinition;

/**
 * Resolve-or-create the agent's default "Lead capture" form from its Leads
 * field settings (`agent.settings('leads')`). Both the inline render tool and
 * the conversational capture path use it, so an agent captures contacts without
 * first building a form — every capture lands as a submission under this form.
 */
class DefaultLeadForm
{
    public const SLUG = 'lead-capture';

    private const FIELDS = ['name', 'email', 'phone', 'job_title', 'company', 'location', 'message'];

    public function forAgent(Agent $agent): FormDefinition
    {
        $settings = $agent->settings('leads')->toArray();
        $fieldsConfig = $settings['fields'] ?? [];

        $schema = [];
        foreach (self::FIELDS as $key) {
            $cfg = $fieldsConfig[$key] ?? null;
            if (! is_array($cfg)) {
                continue;
            }
            $schema[] = [
                'key' => $key,
                'label' => $cfg['title'] ?? ucfirst(str_replace('_', ' ', $key)),
                'type' => match ($key) {
                    'email' => 'email',
                    'phone' => 'tel',
                    'message' => 'textarea',
                    default => 'text',
                },
                'required' => (bool) ($cfg['required'] ?? false),
            ];
        }

        return FormDefinition::firstOrCreate(
            ['agent_id' => $agent->id, 'slug' => self::SLUG],
            [
                'name' => 'Lead capture',
                'form_type' => FormType::Lead,
                'schema' => $schema,
                'success_message' => $settings['success']['message'] ?? null,
                'is_active' => true,
            ],
        );
    }

    /**
     * The required field keys for the default lead form (drives the
     * conversational tool's "missing fields" prompt).
     *
     * @return array<int, string>
     */
    public function requiredFields(Agent $agent): array
    {
        $fieldsConfig = $agent->settings('leads')->toArray()['fields'] ?? [];

        return array_values(array_filter(
            self::FIELDS,
            static fn (string $key): bool => ! empty($fieldsConfig[$key]['required'] ?? false),
        ));
    }
}
