<?php

declare(strict_types=1);

use App\Admin\AdminPanelProvider;
use App\Providers\AdminSettingsServiceProvider;
use App\Providers\ApiDocsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\DatabaseServiceProvider;
use App\Providers\DomainServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\HttpMacroServiceProvider;
use App\Providers\PulseServiceProvider;
use App\Providers\RateLimitServiceProvider;
use App\Providers\SpectraServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;
use Sentry\Laravel\ServiceProvider as SentryServiceProvider;
use Sentry\Laravel\Tracing\ServiceProvider as SentryTracingServiceProvider;

return [
    // First: the other providers boot with the admin panel's saved settings.
    AdminSettingsServiceProvider::class,
    // Not discovered (composer.json): Sentry builds its client while it
    // boots, so it must boot after the DSN from Settings → Platform is in.
    SentryServiceProvider::class,
    SentryTracingServiceProvider::class,
    AppServiceProvider::class,
    HttpMacroServiceProvider::class,
    AuthServiceProvider::class,
    RateLimitServiceProvider::class,
    ApiDocsServiceProvider::class,
    DatabaseServiceProvider::class,
    DomainServiceProvider::class,
    AdminPanelProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
    PulseServiceProvider::class,
    SpectraServiceProvider::class,
    TypeScriptTransformerServiceProvider::class,
];
