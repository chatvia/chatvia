<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Settings;

use Domain\Agent\Data\Settings\AppearanceOverridesData;
use Domain\Agent\Services\AppearanceTextResolver;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the per-language appearance-text overrides edited in the Setup
 * translations dialog. The route already enforces `agent.permission:agent.setup`,
 * so authorize() defers to it. `toData()` keeps only known fields and drops
 * empty values, so a cleared field falls back to the AI translation.
 */
class UpdateAppearanceTranslationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'overrides' => ['present', 'array'],
            'overrides.*' => ['array'],
            'overrides.*.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function toData(): AppearanceOverridesData
    {
        $overrides = (array) $this->input('overrides', []);

        $clean = [];

        foreach (AppearanceTextResolver::KEYS as $key) {
            foreach ((array) ($overrides[$key] ?? []) as $locale => $value) {
                $value = is_string($value) ? trim($value) : '';

                if ($value !== '') {
                    $clean[$key][(string) $locale] = $value;
                }
            }
        }

        return new AppearanceOverridesData(overrides: $clean);
    }
}
