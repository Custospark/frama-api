<?php

namespace App\Providers;

use App\Repositories\Contracts\AssetRepositoryInterface;
use App\Repositories\Eloquent\AssetRepository;
use App\Services\AssetService;
use App\Services\Contracts\AssetServiceInterface;
use Illuminate\Support\ServiceProvider;

class AssetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AssetRepositoryInterface::class, AssetRepository::class);
        $this->app->bind(AssetServiceInterface::class, AssetService::class);
    }
}
