<?php

namespace App\Providers;

use App\Repositories\Contracts\CustomFieldRepositoryInterface;
use App\Repositories\Eloquent\CustomFieldRepository;
use App\Services\Contracts\CustomFieldServiceInterface;
use App\Services\CustomFieldService;
use Illuminate\Support\ServiceProvider;

class CustomFieldServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CustomFieldRepositoryInterface::class, CustomFieldRepository::class);
        $this->app->bind(CustomFieldServiceInterface::class, CustomFieldService::class);
    }
}
