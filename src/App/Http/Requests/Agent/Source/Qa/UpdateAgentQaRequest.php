<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Source\Qa;

use Domain\Knowledge\Source\Qa\Data\QaEntryData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a changed curated Q&A entry and turns it into a QaEntryData.
 * Access to the agent is checked by the route middleware.
 */
class UpdateAgentQaRequest extends FormRequest
{
    use AgentQaRequestRules;

    /**
     * Access to the agent is checked by the route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return $this->qaRules();
    }

    public function toData(): QaEntryData
    {
        return $this->qaEntryData();
    }
}
