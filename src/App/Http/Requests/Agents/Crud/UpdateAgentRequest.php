<?php

namespace App\Http\Requests\Agents\Crud;

use App\Hosting\Rules\SubdomainRule;
use Domain\Agents\Data\Account\UpdateAgentData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateAgentRequest extends FormRequest
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
            'is_public' => ['required', 'boolean'],
        ];
    }

    public function toData(): UpdateAgentData
    {
        return new UpdateAgentData(
            name: $this->string('name')->toString(),
            sub_domain: $this->string('sub_domain')->toString(),
            system_instructions: $this->string('system_instructions')->toString(),
            is_public: $this->boolean('is_public'),
        );
    }
}
