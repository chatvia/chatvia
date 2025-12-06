<?php

namespace App\Features;

use App\Models\User;

class Actions
{
    public string $name = 'actions';

    public function resolve(User $user): mixed
    {
        return $user->type === 'admin';
    }
}
