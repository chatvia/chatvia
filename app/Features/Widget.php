<?php

namespace App\Features;

use App\Models\User;

class Widget
{
    public string $name = 'widget';

    public function resolve(User $user): mixed
    {
        return $user->type === 'admin';
    }
}
