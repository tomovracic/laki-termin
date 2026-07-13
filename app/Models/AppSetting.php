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
        'login_message_updated_at',
        'terrain_usage_rules',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'login_message_updated_at' => 'immutable_datetime',
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
