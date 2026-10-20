<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::Manager,
            'pin_hash' => Hash::make('1234'),
            'is_active' => true,
            'branch_id' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function manager(?Branch $branch = null): static
    {
        return $this->state(fn () => [
            'role' => Role::Manager,
            'branch_id' => $branch?->id ?? Branch::factory(),
        ]);
    }

    public function executive(): static
    {
        return $this->state(fn () => [
            'role' => Role::Executive,
            'branch_id' => null,
        ]);
    }

    public function platformAdmin(): static
    {
        return $this->state(fn () => [
            'role' => Role::PlatformAdmin,
            'branch_id' => null,
        ]);
    }

    public function withPin(string $pin): static
    {
        return $this->state(fn () => [
            'pin_hash' => Hash::make($pin),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
