<?php

use App\Providers\AppServiceProvider;
use App\Providers\DatabaseServiceProvider;
use App\Providers\DomainServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\TelescopeServiceProvider;

return [
    AppServiceProvider::class,
    DomainServiceProvider::class,
    DatabaseServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
    TelescopeServiceProvider::class,
];
