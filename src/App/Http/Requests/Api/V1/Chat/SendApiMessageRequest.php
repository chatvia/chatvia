<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chat;

use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A message sent to an agent over the public API, into a conversation opened
 * over the API. Any other conversation of the agent (hosted chat, widget, a
 * channel) answers 404: a turn loads the whole thread into the model and
 * writes into it, which would let an API caller read and post into a
 * visitor's chat without the `agent.chat.view` permission. Carries no `toData()`:
 * the stream DTO is built by StreamRequestDataFactory::forApi(), the one
 * sanctioned Request-to-domain bridge, so the mapping exists only once.
 */
class SendApiMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');

        abort_unless($conversation instanceof Conversation && $conversation->source === ConversationSource::Api, Response::HTTP_NOT_FOUND);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The message text to send to the agent. The longest message is
            // the install's setting (Settings → Security), as in every chat.
            'message' => ['required', 'string', 'max:'.config('chatvia.guardrails.max_message_chars')],
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
