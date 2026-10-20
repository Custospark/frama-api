<?php

namespace App\Providers;

use App\Repositories\Contracts\BusinessTypeRepositoryInterface;
use App\Repositories\Eloquent\BusinessTypeRepository;
use App\Services\BusinessTypeService;
use App\Services\Contracts\BusinessTypeServiceInterface;
use Illuminate\Support\ServiceProvider;

class BusinessTypeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BusinessTypeRepositoryInterface::class, BusinessTypeRepository::class);
        $this->app->bind(BusinessTypeServiceInterface::class, BusinessTypeService::class);
    }
}
