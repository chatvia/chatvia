<?php

namespace App\Http\Requests\Chat;

use Domain\Agents\Models\Agent;
use Domain\Agents\Rules\AgentLLmChatRule;
use Domain\Chat\Data\CreateConversationData;
use Illuminate\Foundation\Http\FormRequest;

class CreateConversationRequest extends FormRequest
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
            'model' => ['required', 'string', new AgentLLmChatRule],
            'message' => ['required', 'string'],
        ];
    }

    public function toData(Agent $agent): CreateConversationData
    {
        return new CreateConversationData(
            agent: $agent,
            model: $this->string('model')->toString(),
            message: $this->string('message')->toString(),
            ipAddress: $this->ip(),
            userAgent: $this->userAgent(),
            referrerUrl: $this->header('referer') ?: $this->server('HTTP_REFERER'),
            pageUrl: $this->url(),
            locale: $this->header('accept-language') ? substr($this->header('accept-language'), 0, 5) : null,
        );
    }
}
