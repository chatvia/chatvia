<?php

namespace App\Http\Requests\Agents;

use App\Rules\SubdomainRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreateAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'sub_domain' => ['required', new SubdomainRule],
            'system_instructions' => ['required', 'string'],
            'type' => ['required', 'string', 'in:knowledge,instruction'],
        ];
    }
}
