<?php

namespace App\Http\Requests\Agent\Channel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The Facebook Page the workspace picked after the Messenger OAuth round-trip.
 * The id is matched against the Pages held in the session, so an id that was
 * never granted can't be connected. Authorization is the route's
 * `agent.permission:agent.channels` middleware.
 */
class SelectMessengerPageRequest extends FormRequest
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
            'page_id' => ['required', 'string', 'max:64'],
        ];
    }

    public function pageId(): string
    {
        return $this->string('page_id')->toString();
    }
}
