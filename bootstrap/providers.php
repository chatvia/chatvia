<?php

declare(strict_types=1);

use App\Admin\AdminPanelProvider;
use App\Providers\AdminSettingsServiceProvider;
use App\Providers\ApiDocsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\DatabaseServiceProvider;
use App\Providers\DomainServiceProvider;
use App\Providers\FilesystemServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\HttpMacroServiceProvider;
use App\Providers\RateLimitServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;

return [
    // First: the other providers boot with the admin panel's saved settings.
    AdminSettingsServiceProvider::class,
    AppServiceProvider::class,
    HttpMacroServiceProvider::class,
    AuthServiceProvider::class,
    RateLimitServiceProvider::class,
    FilesystemServiceProvider::class,
    ApiDocsServiceProvider::class,
    DatabaseServiceProvider::class,
    DomainServiceProvider::class,
    AdminPanelProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
    TypeScriptTransformerServiceProvider::class,
];
