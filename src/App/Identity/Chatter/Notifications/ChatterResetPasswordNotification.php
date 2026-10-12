<?php

declare(strict_types=1);

namespace App\Identity\Chatter\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

/**
 * The standard "reset your password" email, but its link opens the reset page
 * of the chatter's hosted chat, not the dashboard's.
 */
class ChatterResetPasswordNotification extends ResetPassword
{
    public function __construct(#[\SensitiveParameter] string $token, private readonly string $url)
    {
        parent::__construct($token);
    }

    #[\Override]
    protected function resetUrl($notifiable): string
    {
        return $this->url;
    }
}
