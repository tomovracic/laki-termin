<?php

declare(strict_types=1);

namespace App\Enums;

enum GroupColor: string
{
    case Slate = 'slate';
    case Blue = 'blue';
    case Emerald = 'emerald';
    case Amber = 'amber';
    case Rose = 'rose';
    case Violet = 'violet';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $color): string => $color->value,
            self::cases(),
        );
    }

    /**
     * @return array<int, array{value: string, hex: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $color): array => [
                'value' => $color->value,
                'hex' => $color->hex(),
                'label' => $color->label(),
            ],
            self::cases(),
        );
    }

    public function hex(): string
    {
        return match ($this) {
            self::Slate => '#64748b',
            self::Blue => '#3b82f6',
            self::Emerald => '#10b981',
            self::Amber => '#f59e0b',
            self::Rose => '#f43f5e',
            self::Violet => '#8b5cf6',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Slate => 'Slate',
            self::Blue => 'Blue',
            self::Emerald => 'Emerald',
            self::Amber => 'Amber',
            self::Rose => 'Rose',
            self::Violet => 'Violet',
        };
    }
}
