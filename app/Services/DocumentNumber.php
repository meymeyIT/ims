<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Generates sequential, never-duplicated document numbers per day:
 *   DocumentNumber::next('PO')     -> PO-20261116-0001
 *   DocumentNumber::next('SM', 6)  -> SM-20261116-000001
 *
 * The counter row is locked (SELECT ... FOR UPDATE) until the surrounding
 * transaction commits, so concurrent requests wait instead of colliding.
 */
class DocumentNumber
{
    public static function next(string $prefix, int $padding = 4): string
    {
        $key = strtoupper($prefix) . '-' . now()->format('Ymd');

        return DB::transaction(function () use ($key, $padding) {
            DB::table('sequences')->insertOrIgnore(['name' => $key, 'value' => 0]);

            $value = (int) DB::table('sequences')->where('name', $key)->lockForUpdate()->value('value') + 1;

            DB::table('sequences')->where('name', $key)->update(['value' => $value]);

            return $key . '-' . str_pad((string) $value, $padding, '0', STR_PAD_LEFT);
        });
    }
}
