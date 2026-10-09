<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Crud;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgentTemperatureRequest extends FormRequest
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
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
        ];
    }
}
