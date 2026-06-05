<?php

namespace App\Http\Requests\Api\V1\Agent;

use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Data\Agent\AttachAgentMcpData;
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
            'mcp_id' => ['required', 'string', 'uuid'],
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
