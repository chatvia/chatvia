<?php

namespace App\Http\Requests\Api\V1\Agent;

use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Data\Agent\AttachAgentMcpData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Public API: validates attaching one of the workspace's MCP servers to an
 * agent by the server's id. The `agent.sources.train.connectors` permission is
 * checked by the route middleware.
 */
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
