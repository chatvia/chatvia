<?php

namespace App\Features;

use App\Auth\Models\User;

class Widget
{
    public string $name = 'widget';

    public function resolve(User $user): mixed
    {
        return $user->isAdmin();
    }
}
