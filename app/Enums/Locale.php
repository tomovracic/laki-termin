<?php

declare(strict_types=1);

namespace App\Enums;

enum Locale: string
{
    case Croatian = 'hr';
    case English = 'en';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $locale): string => $locale->value,
            self::cases(),
        );
    }

    /**
     * @return array<int, array{code: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $locale): array => [
                'code' => $locale->value,
                'label' => $locale->label(),
            ],
            self::cases(),
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Croatian => 'Hrvatski',
            self::English => 'English',
        };
    }
}
