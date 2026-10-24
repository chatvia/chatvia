<?php

namespace App\Http\Requests\Agent\ChatLog;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\TeamPermissionPolicy;
use Domain\Agent\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a corrected answer an operator writes in the chat log for one of
 * the agent's replies; it becomes a Q&A entry future questions are answered
 * from and, when asked, an eval case that keeps checking it. Access to the
 * agent is checked by route middleware; adding an eval case also needs the
 * eval permission.
 */
class ReviseAnswerRequest extends FormRequest
{
    public function authorize(TeamPermissionPolicy $permissions): bool
    {
        if (! $this->boolean('add_eval_case')) {
            return true;
        }

        $user = $this->user();
        $agent = $this->route('agent');

        return $user instanceof User
            && $agent instanceof Agent
            && $permissions->allowsOnAgent($user, $agent, 'agent.evals.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message_id' => ['required', 'string'],
            'user_message' => ['required', 'string', 'max:5000'],
            'expected_response' => ['required', 'string', 'max:10000'],
            'add_eval_case' => ['sometimes', 'boolean'],
        ];
    }

    public function messageId(): string
    {
        return $this->string('message_id')->toString();
    }

    public function userMessage(): string
    {
        return $this->string('user_message')->toString();
    }

    public function expectedResponse(): string
    {
        return $this->string('expected_response')->toString();
    }

    public function addEvalCase(): bool
    {
        return $this->boolean('add_eval_case');
    }
}
