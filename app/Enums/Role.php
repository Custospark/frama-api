<?php

namespace App\Enums;

enum Role: string
{
    case Manager = 'manager';
    case Executive = 'executive';
    case PlatformAdmin = 'platform_admin';

    public function rank(): int
    {
        return match ($this) {
            self::Manager => 1,
            self::Executive => 2,
            self::PlatformAdmin => 3,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Manager',
            self::Executive => 'Executive',
            self::PlatformAdmin => 'Platform Admin',
        };
    }

    public function atLeast(self $role): bool
    {
        return $this->rank() >= $role->rank();
    }

    public function managesBranch(): bool
    {
        return $this === self::Manager;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
