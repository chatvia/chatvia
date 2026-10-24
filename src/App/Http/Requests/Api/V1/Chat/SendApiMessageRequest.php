<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * A message sent to an agent over the public API. Carries no `toData()`:
 * the stream DTO is built by StreamRequestDataFactory::forApi(), the one
 * sanctioned Request-to-domain bridge, so the mapping exists only once.
 */
class SendApiMessageRequest extends FormRequest
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
            // The message text to send to the agent.
            'message' => ['required', 'string', 'max:32000'],
            // `true` streams the reply as server-sent events. Sending an
            // `Accept: text/event-stream` header does the same.
            'stream' => ['sometimes', 'boolean'],
            // Your idempotency key. Resending the same value replays the
            // finished reply instead of generating (and counting) a new one.
            'request_id' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }

    /** Whether the reply goes out as server-sent events instead of JSON. */
    public function wantsStream(): bool
    {
        return $this->boolean('stream')
            || Str::contains((string) $this->header('Accept'), 'text/event-stream');
    }
}
