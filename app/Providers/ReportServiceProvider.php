<?php

namespace App\Providers;

use App\Repositories\Contracts\ReportRepositoryInterface;
use App\Repositories\Eloquent\ReportRepository;
use App\Services\Contracts\ReportServiceInterface;
use App\Services\ReportService;
use App\Services\WhatsAppNotifier;
use Illuminate\Support\ServiceProvider;

class ReportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReportRepositoryInterface::class, ReportRepository::class);
        $this->app->singleton(WhatsAppNotifier::class);
        $this->app->bind(ReportServiceInterface::class, ReportService::class);
    }
}
