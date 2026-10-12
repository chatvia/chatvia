<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Identity\Chatter\Data\ResetChatterPasswordWithTokenData;
use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * The hosted chat's "choose a new password" form, reached from a chatter's
 * reset email. The chatter is looked up only inside the agent's workspace; an
 * unknown chatter fails like a bad token, so the form reveals nothing.
 */
class ResetChatterPasswordRequest extends FormRequest
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
            'token' => ['required', 'string'],
            'chatter' => ['required', 'uuid'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @throws ValidationException when the chatter is not in this workspace
     */
    public function toData(Agent $agent): ResetChatterPasswordWithTokenData
    {
        $chatter = Chatter::query()
            ->where('workspace_id', $agent->workspace_id)
            ->where('external_id', $this->string('chatter')->toString())
            ->first();

        if (! $chatter instanceof Chatter) {
            throw ValidationException::withMessages(['token' => trans('passwords.token')]);
        }

        return new ResetChatterPasswordWithTokenData(
            chatter: $chatter,
            token: $this->string('token')->toString(),
            password: $this->string('password')->toString(),
        );
    }
}
