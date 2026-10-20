<?php

namespace App\Providers;

use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Eloquent\BranchRepository;
use App\Services\BranchService;
use App\Services\BranchShareService;
use App\Services\Contracts\BranchServiceInterface;
use App\Services\Contracts\BranchShareServiceInterface;
use Illuminate\Support\ServiceProvider;

class BranchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BranchRepositoryInterface::class, BranchRepository::class);
        $this->app->bind(BranchServiceInterface::class, BranchService::class);
        $this->app->bind(BranchShareServiceInterface::class, BranchShareService::class);
    }
}
