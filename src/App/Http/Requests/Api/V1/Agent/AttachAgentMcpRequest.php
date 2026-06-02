<?php

namespace App\Http\Requests\Api\V1\Agent;

use Domain\Mcp\Data\Agent\AttachAgentMcpData;
use Domain\Agent\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;

class AttachAgentMcpRequest extends FormRequest
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
            'mcp_id' => ['required', 'string'],
        ];
    }

    public function toData(Agent $agent): AttachAgentMcpData
    {
        return new AttachAgentMcpData(
            agent: $agent,
            mcp_id: $this->string('mcp_id')->toString(),
        );
    }
}
