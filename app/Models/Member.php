<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $guarded = ['id'];

    protected $hidden = [
        'password',
        'remember_token',
    ];
}
