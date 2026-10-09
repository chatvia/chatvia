<?php

namespace App\Http\Requests\Agent\Channel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The Instagram professional account the workspace picked after the Instagram
 * OAuth round-trip. The id is matched against the accounts held in the
 * session, so an id that was never granted can't be connected. Authorization
 * is the route's `agent.permission:agent.channels` middleware.
 */
class SelectInstagramAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'ig_id' => ['required', 'string', 'max:64'],
        ];
    }

    public function igId(): string
    {
        return $this->string('ig_id')->toString();
    }
}
