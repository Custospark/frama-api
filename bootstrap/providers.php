<?php

use App\Providers\AppServiceProvider;
use App\Providers\AssetServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\BranchServiceProvider;
use App\Providers\BusinessTypeServiceProvider;
use App\Providers\CustomFieldServiceProvider;
use App\Providers\DashboardServiceProvider;
use App\Providers\ReportServiceProvider;
use App\Providers\UserServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    BranchServiceProvider::class,
    AssetServiceProvider::class,
    BusinessTypeServiceProvider::class,
    CustomFieldServiceProvider::class,
    ReportServiceProvider::class,
    DashboardServiceProvider::class,
    UserServiceProvider::class,
];
