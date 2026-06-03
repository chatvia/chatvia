<?php

namespace App\Http\Requests\Api\V1\Agent;

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Data\AttachAgentFunctionData;
use Illuminate\Foundation\Http\FormRequest;

class AttachAgentFunctionRequest extends FormRequest
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
            'function_id' => ['required', 'string'],
        ];
    }

    public function toData(Agent $agent): AttachAgentFunctionData
    {
        return new AttachAgentFunctionData(
            agent: $agent,
            function_id: $this->string('function_id')->toString(),
        );
    }
}
