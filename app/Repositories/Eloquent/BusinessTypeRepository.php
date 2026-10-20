<?php

namespace App\Repositories\Eloquent;

use App\Models\BusinessType;
use App\Repositories\Contracts\BusinessTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class BusinessTypeRepository implements BusinessTypeRepositoryInterface
{
    public function all(bool $activeOnly = false): Collection
    {
        return BusinessType::query()
            ->withCount(['assets', 'customFields'])
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): ?BusinessType
    {
        return BusinessType::find($id);
    }

    public function create(array $data): BusinessType
    {
        return BusinessType::create($data);
    }

    public function update(BusinessType $businessType, array $data): BusinessType
    {
        $businessType->update($data);

        return $businessType->fresh();
    }

    public function delete(BusinessType $businessType): bool
    {
        return $businessType->delete();
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        return BusinessType::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }
}
