<?php

namespace App\Enums;

enum CustomFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case Checkbox = 'checkbox';
    case File = 'file';
    case Calculated = 'calculated';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Short text',
            self::Textarea => 'Long text',
            self::Number => 'Number',
            self::Date => 'Date',
            self::Select => 'Dropdown',
            self::Checkbox => 'Checkbox',
            self::File => 'File / Photo',
            self::Calculated => 'Calculated',
        };
    }

    /** Whether this type stores an uploaded file path. */
    public function isFile(): bool
    {
        return $this === self::File;
    }

    /** Whether this type's value is derived rather than user-entered. */
    public function isCalculated(): bool
    {
        return $this === self::Calculated;
    }

    /** Whether this type's value is numeric (used for aggregation). */
    public function isNumeric(): bool
    {
        return $this === self::Number || $this === self::Calculated;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
