<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Low-stock rule from the spec: current_stock <= minimum_stock.
 *
 * Duplicate prevention: products.low_stock_alerted_at marks "an alert was already
 * sent for this low-stock period". It is set with a single conditional UPDATE, so
 * even two simultaneous stock-outs can only produce ONE alert. When the product is
 * restocked above its minimum the mark is cleared (and its open alerts are marked
 * read), so the next time it drops, a new alert is sent.
 */
class LowStockMonitor
{
    /** Called after every stock change and after editing a product's minimum stock. */
    public function check(int $productId): void
    {
        if (Setting::get('low_stock_notifications', true)) {
            $claimed = DB::table('products')
                ->where('id', $productId)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->whereNull('low_stock_alerted_at')
                ->whereColumn('current_stock', '<=', 'minimum_stock')
                ->update(['low_stock_alerted_at' => now()]);

            if ($claimed === 1) {
                $this->notify(Product::with('unit')->findOrFail($productId));

                return;
            }
        }

        $cleared = DB::table('products')
            ->where('id', $productId)
            ->whereNotNull('low_stock_alerted_at')
            ->where(fn ($q) => $q->whereColumn('current_stock', '>', 'minimum_stock')
                ->orWhere('is_active', false)
                ->orWhereNotNull('deleted_at'))
            ->update(['low_stock_alerted_at' => null]);

        if ($cleared === 1) {
            $this->resolveOpenAlerts($productId);
        }
    }

    /**
     * Full scan (scheduled daily): catches products that became low without a stock
     * movement, e.g. alerts were switched on later. Returns how many products changed.
     */
    public function scan(): int
    {
        $ids = Product::query()
            ->where(fn ($q) => $q
                ->where(fn ($low) => $low->whereNull('low_stock_alerted_at')->where('is_active', true)->whereColumn('current_stock', '<=', 'minimum_stock'))
                ->orWhere(fn ($ok) => $ok->whereNotNull('low_stock_alerted_at')->whereColumn('current_stock', '>', 'minimum_stock')))
            ->pluck('id');

        $ids->each(fn (int $id) => $this->check($id));

        return $ids->count();
    }

    /** Admins + every active user whose role has the notifications.view permission. */
    public function recipients()
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($role) => $role->where(fn ($q) => $q
                ->where('name', Role::ADMIN)
                ->orWhereHas('permissions', fn ($p) => $p->where('name', 'notifications.view'))))
            ->get();
    }

    private function notify(Product $product): void
    {
        Notification::send($this->recipients(), new LowStockNotification($product));
    }

    private function resolveOpenAlerts(int $productId): void
    {
        DatabaseNotification::query()
            ->where('type', 'low_stock')
            ->whereNull('read_at')
            ->where('data->product_id', $productId)
            ->update(['read_at' => now()]);
    }
}
