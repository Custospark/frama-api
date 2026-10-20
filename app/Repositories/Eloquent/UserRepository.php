<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class UserRepository implements UserRepositoryInterface
{
    public function all(): Collection
    {
        return User::with('branch')->orderBy('name')->get();
    }

    public function find(int $id): ?User
    {
        return User::with('branch')->find($id);
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh('branch');
    }

    public function delete(User $user): bool
    {
        return $user->delete();
    }
}
