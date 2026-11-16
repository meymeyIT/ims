<?php

namespace App\Support;

/**
 * Displays stored DECIMAL(15,3) quantities without useless zeros:
 * "12.000" -> "12", "2.500" -> "2.5", "1250.000" -> "1,250".
 */
class Quantity
{
    public static function format(string|int|float|null $quantity): string
    {
        $formatted = number_format((float) $quantity, 3);

        return str_contains($formatted, '.') ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }
}
