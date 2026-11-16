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
use App\Support\Branding;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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

        // Permission names (they contain a dot, e.g. "products.create") are checked
        // against the user's role. This makes these all work with permission names:
        //   route middleware  ->middleware('can:products.create')
        //   Blade             @can('products.create')
        //   PHP               $user->can('products.create'), Gate::authorize(...)
        // Abilities without a dot (viewAny, update, delete...) go to the model Policies.
        Gate::before(function (User $user, string $ability) {
            if (str_contains($ability, '.')) {
                return $user->hasPermission($ability) ? true : null;
            }

            return null;
        });

        // Password rules used everywhere (create user, reset, change password).
        Password::defaults(fn () => Password::min(8)->letters()->mixedCase()->numbers());

        // Laravel's paginator renders Bootstrap 5 markup.
        Paginator::useBootstrapFive();

        // Business name and logo for every layout (fall back safely before settings exist).
        View::composer('layouts.*', function ($view) {
            $view->with('businessName', Branding::name())
                ->with('businessLogo', Branding::logoUrl());
        });

        // Dates everywhere follow the format chosen in Settings:
        //   $date->toAppDate()      e.g. 16/11/2026
        //   $date->toAppDateTime()  e.g. 16/11/2026 14:30
        foreach ([Carbon::class, CarbonImmutable::class] as $carbon) {
            $carbon::macro('toAppDate', function () {
                /** @var Carbon $this */
                return $this->format(Branding::dateFormat());
            });
            $carbon::macro('toAppDateTime', function () {
                /** @var Carbon $this */
                return $this->format(Branding::dateTimeFormat());
            });
        }
    }
}
