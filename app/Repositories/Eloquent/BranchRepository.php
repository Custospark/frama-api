<?php

namespace App\Repositories\Eloquent;

use App\Models\Branch;
use App\Repositories\Contracts\BranchRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class BranchRepository implements BranchRepositoryInterface
{
    public function all(): Collection
    {
        return Branch::with('businessType')->withCount('assets')->orderBy('branch_name')->get();
    }

    public function find(int $id): ?Branch
    {
        return Branch::with('businessType')->withCount('assets')->find($id);
    }

    public function findByCode(string $code): ?Branch
    {
        return Branch::where('code', strtoupper($code))->first();
    }

    public function findBySlug(string $slug): ?Branch
    {
        return Branch::where('slug', $slug)->first();
    }

    public function create(array $data): Branch
    {
        return Branch::create($data);
    }

    public function update(Branch $branch, array $data): Branch
    {
        $branch->update($data);

        return $branch->fresh();
    }

    public function delete(Branch $branch): bool
    {
        return $branch->delete();
    }

    public function codeExists(string $code, ?int $ignoreId = null): bool
    {
        return Branch::where('code', strtoupper($code))
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }
}
