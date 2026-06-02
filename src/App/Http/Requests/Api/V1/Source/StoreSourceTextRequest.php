<?php

namespace App\Http\Requests\Api\V1\Source;

use Illuminate\Foundation\Http\FormRequest;

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
            'content' => ['required', 'string', 'min:500'],
        ];
    }
}
