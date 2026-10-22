<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Audit;

use App\Http\Requests\Api\V1\CursorPageRequest;
use Dedoc\Scramble\Attributes\QueryParameter;
use Domain\Integration\Tool\Enums\ToolCallOutcome;
use Illuminate\Validation\Rule;

/**
 * The audit-log lists' paging plus their declared filters: the gateway
 * outcome and the catalog connector the tool belongs to.
 */
#[QueryParameter('per_page', description: 'How many items to return (1 to 100).', type: 'int', default: self::DEFAULT_PER_PAGE)]
#[QueryParameter('cursor', description: 'The `next_cursor` of the previous page. Leave it out for the first page.', type: 'string')]
#[QueryParameter('outcome', description: 'Only calls with this gateway outcome: `auto`, `approved`, `denied`, `rate_limited` or `not_offered`.', type: 'string')]
#[QueryParameter('connector', description: 'Only calls of this catalog connector (its slug).', type: 'string')]
class AuditLogRequest extends CursorPageRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'outcome' => ['sometimes', 'nullable', Rule::enum(ToolCallOutcome::class)],
            'connector' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /** The outcome filter, or null for every outcome. */
    public function outcome(): ?string
    {
        $outcome = $this->validated('outcome');

        return filled($outcome) ? $outcome : null;
    }

    /** The connector-slug filter, or null for every connector. */
    public function connector(): ?string
    {
        $connector = $this->validated('connector');

        return filled($connector) ? $connector : null;
    }
}
