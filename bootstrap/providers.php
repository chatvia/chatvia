<?php

use App\Admin\AdminPanelProvider;
use App\Providers\AppServiceProvider;
use App\Providers\DatabaseServiceProvider;
use App\Providers\DomainServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\TelescopeServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;

return [
    AppServiceProvider::class,
    DatabaseServiceProvider::class,
    DomainServiceProvider::class,
    AdminPanelProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
    TelescopeServiceProvider::class,
    TypeScriptTransformerServiceProvider::class,
];
