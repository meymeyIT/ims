<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Role;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Store short names like "purchase_receipt_item" in *_type columns instead of
        // full PHP class names, so renaming a class never breaks old history rows.
        Relation::enforceMorphMap([
            'user' => User::class,
            'role' => Role::class,
            'category' => Category::class,
            'unit' => Unit::class,
            'product' => Product::class,
            'supplier' => Supplier::class,
            'purchase' => Purchase::class,
            'purchase_item' => PurchaseItem::class,
            'purchase_receipt' => PurchaseReceipt::class,
            'purchase_receipt_item' => PurchaseReceiptItem::class,
            'stock_movement' => StockMovement::class,
            'setting' => Setting::class,
            'activity_log' => ActivityLog::class,
        ]);

        // In development, throw an error on lazy loading (catches N+1 queries early),
        // on silently discarded attributes and on accessing missing attributes.
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
