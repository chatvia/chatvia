<?php

namespace App\Http\Controllers\Billing\Usage;

use App\Auth\Data\Resources\My\Usage\UsageResourceData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UsageController
{
    public function __invoke(Request $request): Response|UsageResourceData
    {
        if ($request->wantsJson()) {
            return UsageResourceData::fromUser($request->user());
        }

        return Inertia::render('Usage');
    }
}
