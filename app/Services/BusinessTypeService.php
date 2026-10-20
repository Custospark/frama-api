<?php

namespace App\Services;

use App\Models\BusinessType;
use App\Repositories\Contracts\BusinessTypeRepositoryInterface;
use App\Services\Contracts\BusinessTypeServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class BusinessTypeService implements BusinessTypeServiceInterface
{
    public function __construct(
        protected BusinessTypeRepositoryInterface $businessTypeRepository,
    ) {}

    public function list(bool $activeOnly = false): Collection
    {
        return $this->businessTypeRepository->all($activeOnly);
    }

    public function create(array $data): BusinessType
    {
        $data['slug'] = $this->resolveSlug($data['slug'] ?? null, $data['name']);

        if ($this->businessTypeRepository->slugExists($data['slug'])) {
            throw new RuntimeException('A business type with this slug already exists.');
        }

        return $this->businessTypeRepository->create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'icon' => $data['icon'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);
    }

    public function update(int $id, array $data): BusinessType
    {
        $businessType = $this->businessTypeRepository->find($id);

        if (! $businessType) {
            throw new RuntimeException('Business type not found.');
        }

        if (array_key_exists('slug', $data) && $data['slug']) {
            if ($this->businessTypeRepository->slugExists($data['slug'], $id)) {
                throw new RuntimeException('A business type with this slug already exists.');
            }
        } elseif (array_key_exists('name', $data)) {
            $data['slug'] = $this->resolveSlug(null, $data['name'], $id);
        }

        return $this->businessTypeRepository->update($businessType, $data);
    }

    public function delete(int $id): bool
    {
        $businessType = $this->businessTypeRepository->find($id);

        if (! $businessType) {
            throw new RuntimeException('Business type not found.');
        }

        return $this->businessTypeRepository->delete($businessType);
    }

    private function resolveSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        if ($slug) {
            return $slug;
        }

        $base = Str::slug($name, '_');
        $candidate = $base;
        $suffix = 1;

        while ($this->businessTypeRepository->slugExists($candidate, $ignoreId)) {
            $candidate = $base . '_' . $suffix++;
        }

        return $candidate;
    }
}
