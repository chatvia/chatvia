<?php

namespace App\Http\Requests\Widget;

use Domain\Agents\Data\CollectLeadData;
use Domain\Agents\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;

class CollectVisitorLeadRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:255'],
            'job_title' => ['sometimes', 'string', 'max:255'],
            'company' => ['sometimes', 'string', 'max:255'],
            'location' => ['sometimes', 'string', 'max:255'],
            'message' => ['sometimes', 'string'],
            'referrer_url' => ['nullable', 'string', 'max:255'],
            'page_url' => ['nullable', 'string', 'max:255'],
            'locale' => ['nullable', 'string', 'max:255'],
            'user_agent' => ['sometimes', 'string', 'max:255'],
        ];
    }

    public function toData(Agent $agent): CollectLeadData
    {
        return new CollectLeadData(
            agent: $agent,
            threadId: (string) $this->header('X-THREAD-ID'),
            sessionId: (string) $this->header('X-SESSION-ID'),
            email: $this->input('email'),
            name: $this->input('name'),
            phone: $this->input('phone'),
            jobTitle: $this->input('job_title'),
            company: $this->input('company'),
            location: $this->input('location'),
            message: $this->input('message'),
            userAgent: $this->input('user_agent'),
            referrerUrl: $this->input('referrer_url') ?? $this->input('referrer'),
            pageUrl: $this->input('page_url'),
            locale: $this->input('locale'),
            ipAddress: $this->ip(),
        );
    }
}
