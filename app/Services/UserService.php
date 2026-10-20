<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Branch;
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

        if ($role === Role::Manager) {
            $data['access_code'] = $this->resolveManagerAccessCode((int) $data['branch_id'], null);
        } else {
            $data['branch_id'] = null;
            $data['access_code'] = strtoupper(trim((string) ($data['access_code'] ?? '')));

            if ($data['access_code'] === '') {
                throw new RuntimeException('An access code is required.');
            }

            if ($this->accessCodeTaken($data['access_code'])) {
                throw new RuntimeException('That access code is already taken.');
            }
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

        $role = isset($data['role']) ? Role::from($data['role']) : $user->role;
        $branchId = array_key_exists('branch_id', $data) ? $data['branch_id'] : $user->branch_id;

        if (isset($data['role']) || array_key_exists('branch_id', $data)) {
            if ($role === Role::Manager && empty($branchId)) {
                throw new RuntimeException('A manager must be assigned to a branch.');
            }

            if ($role !== Role::Manager) {
                $data['branch_id'] = null;
            }
        }

        if ($role === Role::Manager) {
            if (! empty($branchId)) {
                $data['access_code'] = $this->resolveManagerAccessCode((int) $branchId, $user->id);
            }
        } elseif (! empty($data['access_code'])) {
            $data['access_code'] = strtoupper(trim($data['access_code']));
        } else {
            unset($data['access_code']);
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

    protected function resolveManagerAccessCode(int $branchId, ?int $exceptUserId): string
    {
        $code = strtoupper((string) Branch::query()->where('id', $branchId)->value('code'));

        if ($code === '') {
            throw new RuntimeException('A manager must be assigned to a branch.');
        }

        if ($this->accessCodeTaken($code, $exceptUserId)) {
            throw new RuntimeException('That branch already has a manager.');
        }

        return $code;
    }

    protected function accessCodeTaken(string $accessCode, ?int $exceptUserId = null): bool
    {
        return User::query()
            ->whereRaw('UPPER(access_code) = ?', [$accessCode])
            ->when($exceptUserId, fn ($query) => $query->where('id', '!=', $exceptUserId))
            ->exists();
    }
}
