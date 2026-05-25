<?php

use App\Providers\AppServiceProvider;
use App\Providers\DatabaseServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\TelescopeServiceProvider;

return [
    AppServiceProvider::class,
    DatabaseServiceProvider::class,
    HorizonServiceProvider::class,
    TelescopeServiceProvider::class,
];
