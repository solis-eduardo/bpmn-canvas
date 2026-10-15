<?php

use App\Providers\AccessMemoServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AssetServiceProvider;
use App\Providers\AuthServiceProvider;

return [
    AppServiceProvider::class,
    AccessMemoServiceProvider::class,
    AssetServiceProvider::class,
    AuthServiceProvider::class,
];
