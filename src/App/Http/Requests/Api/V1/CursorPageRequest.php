<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The paging parameters every public-API list accepts: `cursor` (the
 * `next_cursor` of the previous page) and `per_page`. A `per_page` outside 1
 * to 100 is brought into that range, not rejected. List requests with their
 * own filters extend this class (and repeat the two attributes, which PHP
 * does not inherit).
 *
 * The API docs take a list's paging parameters from these class attributes:
 * on a list that has a Form Request, attributes on the controller method lose
 * to the parameters read from the rules.
 */
#[QueryParameter('per_page', description: 'How many items to return (1 to 100).', type: 'int', default: self::DEFAULT_PER_PAGE)]
#[QueryParameter('cursor', description: 'The `next_cursor` of the previous page. Leave it out for the first page.', type: 'string')]
class CursorPageRequest extends FormRequest
{
    public const int DEFAULT_PER_PAGE = 25;

    public const int MAX_PER_PAGE = 100;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'cursor' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'per_page' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    /** How many items the page holds: `per_page` clamped to 1–100, 25 when left out. */
    public function perPage(): int
    {
        if (! $this->filled('per_page')) {
            return self::DEFAULT_PER_PAGE;
        }

        return min(max(1, $this->integer('per_page')), self::MAX_PER_PAGE);
    }

    /** The cursor the client sent back, or null for the first page. */
    public function cursor(): ?string
    {
        $cursor = $this->query('cursor');

        return filled($cursor) && ! is_array($cursor) ? $cursor : null;
    }
}
