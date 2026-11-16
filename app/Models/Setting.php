<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Key/value settings with a cache in front.
 *
 * Usage:
 *   Setting::get('business_name');
 *   Setting::get('khr_exchange_rate', 4000);
 *   Setting::set('currency', 'KHR');
 */
class Setting extends Model
{
    private const CACHE_KEY = 'settings.all';

    protected $fillable = ['key', 'value', 'type', 'group'];

    protected static function booted(): void
    {
        // Any change clears the cache so the next read sees fresh values.
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever(self::CACHE_KEY, function () {
            return static::query()->get(['key', 'value', 'type'])
                ->mapWithKeys(fn (self $s) => [$s->key => $s->castValue()])
                ->all();
        });

        return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $setting = static::firstOrNew(['key' => $key]);

        $setting->value = match (true) {
            is_bool($value) => $value ? '1' : '0',
            is_array($value) => json_encode($value),
            default => $value === null ? null : (string) $value,
        };

        $setting->save();
    }

    public function castValue(): mixed
    {
        if ($this->value === null) {
            return null;
        }

        return match ($this->type) {
            'integer' => (int) $this->value,
            'decimal' => (string) $this->value,   // keep as string for bcmath precision
            'boolean' => in_array($this->value, ['1', 'true', 'on', 'yes'], true),
            'json' => json_decode($this->value, true),
            default => $this->value,               // string, file (storage path)
        };
    }
}
