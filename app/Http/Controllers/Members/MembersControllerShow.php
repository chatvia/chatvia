<?php

namespace App\Http\Controllers\Members;

use Illuminate\Http\Request;
use Inertia\Inertia;

class MembersControllerShow
{
    public function __invoke(Request $request)
    {
        return Inertia::render('members/Index');
    }
}
