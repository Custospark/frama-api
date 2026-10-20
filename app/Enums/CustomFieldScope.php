<?php

namespace App\Enums;

enum CustomFieldScope: string
{
    case Asset = 'asset';
    case Report = 'report';

    public function label(): string
    {
        return match ($this) {
            self::Asset => 'Property',
            self::Report => 'Report',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
