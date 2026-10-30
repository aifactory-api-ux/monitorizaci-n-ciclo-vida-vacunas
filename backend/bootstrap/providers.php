<?php

declare(strict_types=1);
use App\Api\Providers\AppServiceProvider;
use App\Api\Providers\BroadcastServiceProvider;
use App\Api\Providers\RouteServiceProvider;

return [
    AppServiceProvider::class,
    BroadcastServiceProvider::class,
    RouteServiceProvider::class,
];
