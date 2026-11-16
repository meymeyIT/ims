<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Formats money in the business currency chosen in Settings (USD or KHR).
 * Amounts are stored in that currency; no conversion happens here.
 */
class Currency
{
    public static function code(): string
    {
        return Setting::get('currency', 'USD');
    }

    /** "≈ 72,963,000 ៛" when the business uses USD, "≈ $18.24" when it uses KHR. */
    public static function alternate(string|int|float|null $amount): string
    {
        $rate = (float) Setting::get('khr_exchange_rate', 4000) ?: 4000;

        return self::code() === 'USD'
            ? '≈ ' . number_format((float) $amount * $rate, 0) . ' ៛'
            : '≈ $' . number_format((float) $amount / $rate, 2);
    }

    /**
     * @param  bool  $plain  Use the currency code instead of the symbol (for PDF fonts without ៛).
     * @param  int|null  $decimals  Override the currency's normal decimals (e.g. 4 for unit costs).
     */
    public static function format(string|int|float|null $amount, bool $plain = false, ?int $decimals = null): string
    {
        $code = self::code();
        $config = config("ims.currencies.{$code}", ['symbol' => '$', 'decimals' => 2]);
        $digits = $decimals ?? $config['decimals'];
        $formatted = number_format((float) $amount, $digits);

        // Unit costs: show 4 decimals only when they are really needed.
        if ($decimals !== null && $decimals > $config['decimals'] && str_contains($formatted, '.')) {
            $formatted = rtrim($formatted, '0');
            $minimum = $config['decimals'];
            [$whole, $fraction] = array_pad(explode('.', $formatted), 2, '');
            $formatted = $minimum > 0 || $fraction !== '' ? $whole . '.' . str_pad($fraction, $minimum, '0') : $whole;
            $formatted = rtrim($formatted, '.');
        }

        if ($plain) {
            return "{$formatted} {$code}";
        }

        // Riel is conventionally written after the number: 40,000 ៛
        return $code === 'KHR'
            ? "{$formatted} {$config['symbol']}"
            : "{$config['symbol']}{$formatted}";
    }
}
