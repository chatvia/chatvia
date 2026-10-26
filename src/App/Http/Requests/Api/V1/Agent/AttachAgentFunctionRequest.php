<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Agent;

use App\Http\Requests\Concerns\ScopesRulesToWorkspace;
use App\Http\Rules\WorkspaceRule;
use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Data\AttachAgentFunctionData;
use Domain\Integration\Function\Models\AgentFunction;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Attach one of your workspace's functions to an agent, named by the
 * function's `id`. An id that is not one of your functions fails validation.
 */
class AttachAgentFunctionRequest extends FormRequest
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
            // ID of the function to attach.
            'function_id' => ['bail', 'required', 'string', 'uuid', WorkspaceRule::exists('functions', $this->ruleWorkspaceId(), 'external_id')],
        ];
    }

    public function toData(Agent $agent): AttachAgentFunctionData
    {
        /** @var AgentFunction $function */
        $function = $this->user()->workspace()->functions()
            ->where('external_id', $this->string('function_id')->toString())
            ->firstOrFail();

        return new AttachAgentFunctionData(agent: $agent, function: $function);
    }
}
