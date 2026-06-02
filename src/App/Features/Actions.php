<?php

namespace App\Features;

use App\Workspace\Models\User;

class Actions
{
    public string $name = 'actions';

    public function resolve(User $user): mixed
    {
        return $user->isAdmin();
    }
}
