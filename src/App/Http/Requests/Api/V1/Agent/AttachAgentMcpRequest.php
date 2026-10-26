<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Agent;

use App\Http\Requests\Concerns\ScopesRulesToWorkspace;
use App\Http\Rules\WorkspaceRule;
use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Data\Agent\AttachAgentMcpData;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Attach one of your workspace's MCP servers to an agent, named by the
 * server's `id`. An id that is not one of your servers fails validation.
 */
class AttachAgentMcpRequest extends FormRequest
{
    use ScopesRulesToWorkspace;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * `bail` stops at a malformed id, so it never reaches the uuid column.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // ID of the MCP server to attach.
            'mcp_id' => ['bail', 'required', 'string', 'uuid', WorkspaceRule::exists('mcp_servers', $this->ruleWorkspaceId(), 'external_id')],
        ];
    }

    public function toData(Agent $agent): AttachAgentMcpData
    {
        /** @var AgentMcpServer $server */
        $server = $this->user()->workspace()->mcpServers()
            ->where('external_id', $this->string('mcp_id')->toString())
            ->firstOrFail();

        return new AttachAgentMcpData(agent: $agent, server: $server);
    }
}
