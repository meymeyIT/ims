<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Default system settings. Uses firstOrCreate, so re-running the seeder
 * never overwrites values an Admin has already changed in the Settings page.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // key                          value                           type       group
            ['business_name',               'My Business',                  'string',  'business'],
            ['business_logo',               null,                           'file',    'business'],
            ['business_address',            null,                           'string',  'business'],
            ['business_phone',              null,                           'string',  'business'],
            ['business_email',              null,                           'string',  'business'],

            ['currency',                    'USD',                          'string',  'localization'],  // USD or KHR
            ['khr_exchange_rate',           '4000',                         'decimal', 'localization'],  // 1 USD = x KHR
            ['date_format',                 'd/m/Y',                        'string',  'localization'],
            ['timezone',                    'Asia/Phnom_Penh',              'string',  'localization'],

            ['low_stock_notifications',     '1',                            'boolean', 'notifications'],
            ['low_stock_email',             '0',                            'boolean', 'notifications'],

            ['purchase_number_prefix',      'PO',                           'string',  'system'],
            ['receipt_number_prefix',       'GRN',                          'string',  'system'],
            ['items_per_page',              '15',                           'integer', 'system'],
        ];

        foreach ($defaults as [$key, $value, $type, $group]) {
            Setting::firstOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => $type, 'group' => $group],
            );
        }
    }
}
