<?php

namespace App\Http\Controllers;

use App\Data\Resources\My\Usage\UsageResourceData;
use Illuminate\Http\Request;

class UsageController
{
    public function __invoke(Request $request)
    {
        return UsageResourceData::fromUser($request->user());
    }
}
