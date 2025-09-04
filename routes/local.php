<?php

use App\Models\Llm;

if (app()->isLocal()) {
    Route::get('playground', function () {
        $user = \App\Models\User::query()->first();

        $user->grantPermission(\App\Enums\PermissionEnum::MODELS_VIEW);
    });
}
