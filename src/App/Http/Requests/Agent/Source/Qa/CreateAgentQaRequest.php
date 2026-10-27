<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Source\Qa;

use App\Http\Requests\Concerns\ValidatesQaEntry;
use Domain\Knowledge\Source\Qa\Data\QaEntryData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a new curated Q&A entry for an agent and turns it into a
 * QaEntryData. Access to the agent and the `agent.sources.train.qa` permission
 * are checked by the route middleware.
 */
class CreateAgentQaRequest extends FormRequest
{
    use ValidatesQaEntry;

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
