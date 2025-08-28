<?php

namespace App\Features;

use App\Models\User;

class Workflow
{
    public string $name = 'workflow';

    public function resolve(User $user): mixed
    {
        return $user->is_admin;
    }
}
