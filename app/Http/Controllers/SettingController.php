<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettingRequest;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\LowStockMonitor;
use App\Support\Branding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingController extends Controller implements HasMiddleware
{
    /** Settings edited on this page (the logo is handled separately). */
    private const KEYS = [
        'business_name', 'business_address', 'business_phone', 'business_email',
        'currency', 'khr_exchange_rate', 'date_format',
        'low_stock_notifications', 'low_stock_email',
        'purchase_number_prefix', 'receipt_number_prefix',
    ];

    public static function middleware(): array
    {
        return [new Middleware('can:settings.manage')];
    }

    public function edit(): View
    {
        $values = collect(self::KEYS)->mapWithKeys(fn ($key) => [$key => Setting::get($key)])->all();

        return view('settings.edit', [
            'values' => $values,
            'logoUrl' => Branding::logoUrl(),
            'dateFormats' => Branding::DATE_FORMATS,
        ]);
    }

    public function update(SettingRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $changed = [];

        foreach (self::KEYS as $key) {
            $new = $data[$key] ?? null;
            $old = Setting::get($key);

            if ($this->normalize($key, $new) !== $this->normalize($key, $old)) {
                Setting::set($key, $new);
                $changed[] = $key;
            }
        }

        // Logo: replace or remove; the old file is deleted only after the setting is saved.
        $oldLogo = Setting::get('business_logo');
        if ($request->hasFile('business_logo')) {
            Setting::set('business_logo', $request->file('business_logo')->store('branding', 'public'));
            $changed[] = 'business_logo';
        } elseif ($request->boolean('remove_logo') && $oldLogo) {
            Setting::set('business_logo', null);
            $changed[] = 'business_logo';
        }
        if (in_array('business_logo', $changed, true) && $oldLogo) {
            Storage::disk('public')->delete($oldLogo);
        }

        // Alerts switched back on: send the alerts that were skipped while they were off.
        if (in_array('low_stock_notifications', $changed, true) && $data['low_stock_notifications']) {
            app(LowStockMonitor::class)->scan();
        }

        if ($changed !== []) {
            ActivityLogger::log('updated', 'settings', 'Updated system settings: ' . implode(', ', $changed), properties: ['changed' => $changed]);
        }

        return redirect()->route('settings.edit')->with(
            $changed ? 'success' : 'info',
            $changed ? 'Settings were saved.' : 'Nothing was changed.'
        );
    }

    /** Compare old and new values in the same shape ("4000" == "4000.00", true == "1"). */
    private function normalize(string $key, mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? '1' : '0',
            $value === null => '',
            $key === 'khr_exchange_rate' && is_numeric($value) => rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.'),
            default => trim((string) $value),
        };
    }
}
