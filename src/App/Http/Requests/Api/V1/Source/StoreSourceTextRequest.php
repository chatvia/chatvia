<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Source;

use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a text added as a knowledge source through the public API. Access
 * to the agent is checked by the route middleware.
 */
class StoreSourceTextRequest extends FormRequest
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
            // The text to train on, at least 500 characters.
            'content' => ['required', 'string', 'min:500', 'max:'.SourceText::MAX_CONTENT_CHARS],
        ];
    }

    public function textContent(): string
    {
        return (string) $this->validated('content');
    }
}
