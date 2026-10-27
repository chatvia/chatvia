<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chatter;

use App\Identity\Chatter\Data\UpdateChatterAccessData;
use App\Identity\Chatter\Models\Chatter;
use Illuminate\Foundation\Http\FormRequest;
use Spatie\LaravelData\Optional;

/**
 * Updating a chatter's access controls over the public API: the monthly
 * message cap and the deactivation switch. A field left out keeps its
 * current value.
 */
class UpdateApiChatterRequest extends FormRequest
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
            // The chatter's monthly message cap. Null removes the limit.
            'messages_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            // `true` switches the account off: the chatter can no longer sign
            // in or send messages. `false` switches it back on.
            'deactivated' => ['sometimes', 'boolean'],
            // Shown nowhere publicly; kept for your own records.
            'deactivation_reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function toData(Chatter $chatter): UpdateChatterAccessData
    {
        return new UpdateChatterAccessData(
            chatter: $chatter,
            messagesLimit: match (true) {
                ! $this->has('messages_limit') => new Optional,
                $this->filled('messages_limit') => $this->integer('messages_limit'),
                default => null,
            },
            deactivated: $this->has('deactivated') ? $this->boolean('deactivated') : new Optional,
            deactivationReason: match (true) {
                ! $this->has('deactivation_reason') => new Optional,
                $this->filled('deactivation_reason') => $this->string('deactivation_reason')->toString(),
                default => null,
            },
        );
    }
}
