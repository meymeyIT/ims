<?php

namespace App\Support;

/**
 * Exact decimal maths with bcmath (no floating-point rounding errors).
 * All values are passed and returned as strings, e.g. "12.500".
 */
class Decimal
{
    /** "10" -> "10.000" (scale 3). Truncates extra digits; validation limits the input first. */
    public static function normalize(string|int|float|null $value, int $scale): string
    {
        $value = trim((string) ($value ?? '0'));

        return bcadd($value === '' ? '0' : $value, '0', $scale);
    }

    /** Round half away from zero: round("2.345", 2) = "2.35". */
    public static function round(string $value, int $scale): string
    {
        $half = '0.' . str_repeat('0', $scale) . '5';

        return bccomp($value, '0', $scale + 5) >= 0
            ? bcadd($value, $half, $scale)
            : bcsub($value, $half, $scale);
    }

    public static function isPositive(string $value, int $scale = 3): bool
    {
        return bccomp($value, '0', $scale) > 0;
    }
}
