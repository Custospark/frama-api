<?php

namespace App\Providers;

use App\Services\Contracts\DashboardServiceInterface;
use App\Services\DashboardService;
use Illuminate\Support\ServiceProvider;

class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DashboardServiceInterface::class, DashboardService::class);
    }
}
