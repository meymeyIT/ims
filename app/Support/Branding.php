<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Business name, logo and date formats from System Settings, with safe fallbacks. */
class Branding
{
    public const DATE_FORMATS = [
        'd/m/Y' => '16/11/2026',
        'd-m-Y' => '16-11-2026',
        'm/d/Y' => '11/16/2026',
        'Y-m-d' => '2026-11-16',
        'd M Y' => '16 Nov 2026',
    ];

    public static function name(): string
    {
        return self::setting('business_name', config('app.name'));
    }

    public static function logoUrl(): ?string
    {
        $path = self::setting('business_logo');

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /** Absolute file path (DomPDF needs a file, not a URL). */
    public static function logoPath(): ?string
    {
        $path = self::setting('business_logo');

        return $path && Storage::disk('public')->exists($path) ? Storage::disk('public')->path($path) : null;
    }

    public static function dateFormat(): string
    {
        $format = self::setting('date_format', 'd/m/Y');

        return array_key_exists($format, self::DATE_FORMATS) ? $format : 'd/m/Y';
    }

    public static function dateTimeFormat(): string
    {
        return self::dateFormat() . ' H:i';
    }

    /** Settings may not exist yet (fresh install, migrations running): never crash. */
    private static function setting(string $key, mixed $default = null): mixed
    {
        try {
            return Setting::get($key, $default);
        } catch (Throwable) {
            return $default;
        }
    }
}
