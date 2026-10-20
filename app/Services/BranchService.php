<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Services\Contracts\BranchServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class BranchService implements BranchServiceInterface
{
    public function __construct(
        protected BranchRepositoryInterface $branchRepository,
    ) {}

    public function getAll(): Collection
    {
        return $this->branchRepository->all();
    }

    public function getById(int $id): ?Branch
    {
        return $this->branchRepository->find($id);
    }

    public function create(array $data): Branch
    {
        if (empty($data['code'])) {
            $data['code'] = Branch::generateCode($data['branch_name']);
        }

        $data['code'] = strtoupper($data['code']);

        if ($this->branchRepository->codeExists($data['code'])) {
            throw new RuntimeException('That branch code is already taken.');
        }

        if ($this->accessCodeExists($data['code'])) {
            throw new RuntimeException('That branch code is already taken as a login access code.');
        }

        if (empty($data['slug'])) {
            $data['slug'] = Branch::generateSlug($data['branch_name']);
        }

        return $this->branchRepository->create($data);
    }

    public function update(int $id, array $data): Branch
    {
        $branch = $this->branchRepository->find($id);

        if (! $branch) {
            throw new RuntimeException('Branch not found.');
        }

        $codeChanged = false;

        if (! empty($data['code'])) {
            $data['code'] = strtoupper($data['code']);

            if ($this->branchRepository->codeExists($data['code'], $id)) {
                throw new RuntimeException('That branch code is already taken.');
            }

            $codeChanged = $data['code'] !== strtoupper((string) $branch->code);

            if ($codeChanged && $this->accessCodeExists($data['code'])) {
                throw new RuntimeException('That branch code is already taken as a login access code.');
            }
        }

        $updated = $this->branchRepository->update($branch, $data);

        if ($codeChanged) {
            $this->syncManagerAccessCode($branch->id, $data['code']);
        }

        return $updated;
    }

    public function delete(int $id): bool
    {
        $branch = $this->branchRepository->find($id);

        if (! $branch) {
            throw new RuntimeException('Branch not found.');
        }

        return $this->branchRepository->delete($branch);
    }

    public function regenerateCode(int $id): Branch
    {
        $branch = $this->branchRepository->find($id);

        if (! $branch) {
            throw new RuntimeException('Branch not found.');
        }

        $code = Branch::generateCode($branch->branch_name . '-' . now()->format('His'));

        if ($this->accessCodeExists($code)) {
            throw new RuntimeException('That branch code is already taken as a login access code.');
        }

        $updated = $this->branchRepository->update($branch, ['code' => $code]);

        $this->syncManagerAccessCode($branch->id, $code);

        return $updated;
    }

    protected function syncManagerAccessCode(int $branchId, string $code): void
    {
        User::query()
            ->where('branch_id', $branchId)
            ->where('role', Role::Manager->value)
            ->update(['access_code' => $code]);
    }

    protected function accessCodeExists(string $accessCode): bool
    {
        return User::query()
            ->whereRaw('UPPER(access_code) = ?', [$accessCode])
            ->exists();
    }
}
