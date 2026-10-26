<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The hosted chat's Stop button: the request id of the turn to stop, as the
 * chat sent it with that turn's stream. Whose turn it is comes from the
 * visitor's own chat session.
 */
class StopTurnRequest extends FormRequest
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
            'request_id' => ['required', 'string', 'max:100'],
        ];
    }

    public function requestId(): string
    {
        return $this->string('request_id')->toString();
    }
}
