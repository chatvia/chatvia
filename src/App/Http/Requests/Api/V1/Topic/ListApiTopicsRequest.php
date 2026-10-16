<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Topic;

use App\Http\Requests\Api\V1\CursorPageRequest;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Validation\Rule;

/**
 * The topics list's paging plus its one filter: `scope` picks between the
 * topics attached to the agent (the default) and every topic the workspace
 * may attach.
 */
#[QueryParameter('per_page', description: 'How many items to return (1 to 100).', type: 'int', default: self::DEFAULT_PER_PAGE)]
#[QueryParameter('cursor', description: 'The `next_cursor` of the previous page. Leave it out for the first page.', type: 'string')]
#[QueryParameter('scope', description: '`agent` (default) lists the topics attached to the agent; `available` lists every topic the workspace may attach (its own and the built-in system topics).', type: 'string', default: 'agent')]
class ListApiTopicsRequest extends CursorPageRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'scope' => ['sometimes', 'nullable', Rule::in(['agent', 'available'])],
        ];
    }

    /** Whether the list shows every attachable topic instead of the agent's. */
    public function wantsAvailable(): bool
    {
        return $this->validated('scope') === 'available';
    }
}
