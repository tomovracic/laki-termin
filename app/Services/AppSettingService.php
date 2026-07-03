<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TerrainUsageRuleEmphasis;
use App\Enums\TerrainUsageRuleIcon;
use App\Models\AppSetting;

class AppSettingService
{
    /**
     * @return list<array{icon: string, text: string, emphasis?: string}>
     */
    public function getTerrainUsageRules(): array
    {
        $rules = AppSetting::instance()->terrain_usage_rules;

        if (! is_array($rules)) {
            return [];
        }

        $normalized = [];

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $icon = $rule['icon'] ?? null;
            $text = $rule['text'] ?? null;

            if (! is_string($icon) || ! is_string($text)) {
                continue;
            }

            $trimmedText = trim($text);

            if ($trimmedText === '') {
                continue;
            }

            $iconEnum = TerrainUsageRuleIcon::tryFrom($icon);

            if ($iconEnum === null) {
                continue;
            }

            $entry = [
                'icon' => $iconEnum->value,
                'text' => $trimmedText,
            ];

            $emphasis = $rule['emphasis'] ?? null;

            if (is_string($emphasis)) {
                $emphasisEnum = TerrainUsageRuleEmphasis::tryFrom($emphasis);

                if ($emphasisEnum !== null) {
                    $entry['emphasis'] = $emphasisEnum->value;
                }
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @param  list<array{icon: string, text: string, emphasis?: string|null}>  $rules
     */
    public function updateTerrainUsageRules(array $rules): AppSetting
    {
        $setting = AppSetting::instance();
        $normalized = [];

        foreach ($rules as $rule) {
            $trimmedText = trim($rule['text']);

            if ($trimmedText === '') {
                continue;
            }

            $entry = [
                'icon' => $rule['icon'],
                'text' => $trimmedText,
            ];

            $emphasis = $rule['emphasis'] ?? null;

            if (is_string($emphasis)) {
                $emphasisEnum = TerrainUsageRuleEmphasis::tryFrom($emphasis);

                if ($emphasisEnum !== null) {
                    $entry['emphasis'] = $emphasisEnum->value;
                }
            }

            $normalized[] = $entry;
        }

        $setting->terrain_usage_rules = $normalized === [] ? null : $normalized;
        $setting->save();

        return $setting;
    }

    public function getLoginMessage(): ?string
    {
        $message = AppSetting::instance()->login_message;

        if (! is_string($message)) {
            return null;
        }

        $trimmed = trim($message);

        return $trimmed === '' ? null : $trimmed;
    }

    public function updateLoginMessage(?string $message): AppSetting
    {
        $setting = AppSetting::instance();

        if ($message === null) {
            $setting->login_message = null;
        } else {
            $trimmed = trim($message);
            $setting->login_message = $trimmed === '' ? null : $trimmed;
        }

        $setting->save();

        return $setting;
    }
}
