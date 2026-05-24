<?php

namespace App\Features;

use App\Auth\Models\User;

class Workflow
{
    public string $name = 'workflow';

    public function resolve(User $user): mixed
    {
        return $user->type === 'admin';
    }
}
