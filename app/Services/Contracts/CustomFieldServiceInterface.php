<?php

namespace App\Services\Contracts;

use App\Enums\CustomFieldScope;
use App\Models\CustomField;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface CustomFieldServiceInterface
{
    /**
     * @return Collection<int, CustomField>
     */
    public function list(User $user, ?CustomFieldScope $scope = null, ?int $businessTypeId = null): Collection;

    public function create(User $user, array $data): CustomField;

    public function update(User $user, int $id, array $data): CustomField;

    public function delete(User $user, int $id): bool;

    /**
     * The effective schema for a branch + scope + business type, straight from
     * definitions (used to render forms / build validation).
     *
     * @return Collection<int, CustomField>
     */
    public function schemaFor(?int $branchId, CustomFieldScope $scope, ?int $businessTypeId): Collection;
}
