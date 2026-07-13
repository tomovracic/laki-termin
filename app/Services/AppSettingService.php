<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TerrainUsageRuleEmphasis;
use App\Enums\TerrainUsageRuleIcon;
use App\Models\AppSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

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
        $normalized = [];

        foreach ($rules as $rule) {
            $normalized[] = $this->normalizeTerrainUsageRule($rule);
        }

        return $this->persistTerrainUsageRules($normalized);
    }

    /**
     * @param  array{icon: string, text: string, emphasis?: string|null}  $rule
     */
    public function createTerrainUsageRule(array $rule): AppSetting
    {
        $rules = $this->getTerrainUsageRules();
        $rules[] = $this->normalizeTerrainUsageRule($rule);

        return $this->persistTerrainUsageRules($rules);
    }

    /**
     * @param  array{icon: string, text: string, emphasis?: string|null}  $rule
     */
    public function updateTerrainUsageRule(int $index, array $rule): AppSetting
    {
        $rules = $this->getTerrainUsageRules();

        if (! array_key_exists($index, $rules)) {
            abort(404);
        }

        $rules[$index] = $this->normalizeTerrainUsageRule($rule);

        return $this->persistTerrainUsageRules($rules);
    }

    public function deleteTerrainUsageRule(int $index): AppSetting
    {
        $rules = $this->getTerrainUsageRules();

        if (! array_key_exists($index, $rules)) {
            abort(404);
        }

        array_splice($rules, $index, 1);

        return $this->persistTerrainUsageRules($rules);
    }

    /**
     * @param  array{icon: string, text: string, emphasis?: string|null}  $rule
     * @return array{icon: string, text: string, emphasis?: string}
     */
    private function normalizeTerrainUsageRule(array $rule): array
    {
        $entry = [
            'icon' => $rule['icon'],
            'text' => trim($rule['text']),
        ];

        $emphasis = $rule['emphasis'] ?? null;

        if (is_string($emphasis)) {
            $emphasisEnum = TerrainUsageRuleEmphasis::tryFrom($emphasis);

            if ($emphasisEnum !== null) {
                $entry['emphasis'] = $emphasisEnum->value;
            }
        }

        return $entry;
    }

    /**
     * @param  list<array{icon: string, text: string, emphasis?: string}>  $rules
     */
    private function persistTerrainUsageRules(array $rules): AppSetting
    {
        $setting = AppSetting::instance();
        $setting->terrain_usage_rules = $rules === [] ? null : $rules;
        $setting->save();

        return $setting;
    }

    public function getLoginMessage(): ?string
    {
        return $this->normalizeLoginMessage(AppSetting::instance()->login_message);
    }

    public function getLoginMessageUpdatedAt(): ?CarbonInterface
    {
        return AppSetting::instance()->login_message_updated_at;
    }

    public function updateLoginMessage(?string $message): AppSetting
    {
        $setting = AppSetting::instance();
        $currentMessage = $this->normalizeLoginMessage($setting->login_message);
        $newMessage = $this->normalizeLoginMessage($message);

        $setting->login_message = $newMessage;

        if ($currentMessage !== $newMessage) {
            $setting->login_message_updated_at = $newMessage === null ? null : Date::now();
        }

        $setting->save();

        return $setting;
    }

    private function normalizeLoginMessage(mixed $message): ?string
    {
        if (! is_string($message)) {
            return null;
        }

        $trimmed = trim($message);

        return $trimmed === '' ? null : $trimmed;
    }
}
