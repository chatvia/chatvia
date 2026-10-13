<?php

namespace App\Http\Requests\Auth;

use App\Http\Auth\BotCheck;
use App\Http\Auth\BotCheckForm;
use App\Identity\Workspace\Data\Sessions\LoginData;
use Domain\Agent\Models\Agent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Validates credentials for the agent-scoped chat member login at /chat/login.
 *
 * The dashboard login (/login) is owned by Laravel Fortify and does not use
 * this FormRequest — see Fortify::authenticateUsing() in FortifyServiceProvider.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            ...app(BotCheck::class)->rules(BotCheckForm::SignIn),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return app(BotCheck::class)->messages();
    }

    public function toData(): LoginData
    {
        return new LoginData(
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
            remember: $this->boolean('remember'),
        );
    }

    /**
     * Failed attempts are counted per workspace, email and IP, so a chatter
     * locked out of one workspace's chat is not locked out of another's.
     */
    public function throttleKey(Agent $agent): string
    {
        return Str::transliterate('chat-login|'.$agent->workspace_id.'|'.Str::lower($this->string('email')).'|'.$this->ip());
    }
}
