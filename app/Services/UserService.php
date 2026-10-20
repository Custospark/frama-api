<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\UserServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserService implements UserServiceInterface
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
    ) {}

    public function getAll(): Collection
    {
        return $this->userRepository->all();
    }

    public function getById(int $id): ?User
    {
        return $this->userRepository->find($id);
    }

    public function create(array $data): User
    {
        $role = Role::from($data['role']);

        if ($role === Role::Manager && empty($data['branch_id'])) {
            throw new RuntimeException('A manager must be assigned to a branch.');
        }

        if ($role !== Role::Manager) {
            $data['branch_id'] = null;
        }

        $data['pin_hash'] = Hash::make($data['pin']);
        $data['is_active'] = $data['is_active'] ?? true;
        unset($data['pin']);

        return $this->userRepository->create($data);
    }

    public function update(int $id, array $data): User
    {
        $user = $this->userRepository->find($id);

        if (! $user) {
            throw new RuntimeException('User not found.');
        }

        if (isset($data['role']) || array_key_exists('branch_id', $data)) {
            $role = isset($data['role']) ? Role::from($data['role']) : $user->role;
            $branchId = $data['branch_id'] ?? $user->branch_id;

            if ($role === Role::Manager && empty($branchId)) {
                throw new RuntimeException('A manager must be assigned to a branch.');
            }

            if ($role !== Role::Manager) {
                $data['branch_id'] = null;
            }
        }

        if (! empty($data['pin'])) {
            $data['pin_hash'] = Hash::make($data['pin']);
        }

        unset($data['pin']);

        return $this->userRepository->update($user, $data);
    }

    public function delete(int $id): bool
    {
        $user = $this->userRepository->find($id);

        if (! $user) {
            throw new RuntimeException('User not found.');
        }

        return $this->userRepository->delete($user);
    }

    public function resetPin(int $id, string $pin): User
    {
        $user = $this->userRepository->find($id);

        if (! $user) {
            throw new RuntimeException('User not found.');
        }

        return $this->userRepository->update($user, [
            'pin_hash' => Hash::make($pin),
        ]);
    }
}
