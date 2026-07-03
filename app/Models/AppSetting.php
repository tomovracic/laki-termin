<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'login_message',
        'terrain_usage_rules',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'terrain_usage_rules' => 'array',
        ];
    }

    public static function instance(): self
    {
        /** @var self $setting */
        $setting = static::query()->firstOrCreate(['id' => 1]);

        return $setting;
    }
}
