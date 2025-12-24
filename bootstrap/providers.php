<?php

use App\Providers\RepositoryServiceProvider;
use App\Providers\ServicesServiceProvider;

return [
    App\Providers\AppServiceProvider::class,
    RepositoryServiceProvider::class,
    ServicesServiceProvider::class,
];
