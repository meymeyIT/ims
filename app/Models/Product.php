<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    /**
     * current_stock, average_cost and low_stock_alerted_at are deliberately NOT
     * fillable: only App\Services\StockService may change them (Phase 5/6),
     * always together with a stock_movements row.
     */
    /** Defaults so a freshly created product has these values without a reload. */
    protected $attributes = [
        'current_stock' => '0.000',
        'average_cost' => '0.0000',
        'minimum_stock' => '0.000',
        'is_active' => true,
    ];

    protected $fillable = [
        'category_id',
        'unit_id',
        'name',
        'sku',
        'barcode',
        'purchase_price',
        'selling_price',
        'minimum_stock',
        'image_path',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'average_cost' => 'decimal:4',
            'current_stock' => 'decimal:3',
            'minimum_stock' => 'decimal:3',
            'low_stock_alerted_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    // ---------------------------------------------------------------- relationships

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    // ---------------------------------------------------------------- scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Low stock rule from the spec: current_stock <= minimum_stock. */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('current_stock', '<=', 'minimum_stock');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%");
        });
    }

    // ---------------------------------------------------------------- helpers

    public function isLowStock(): bool
    {
        return bccomp((string) $this->current_stock, (string) $this->minimum_stock, 3) <= 0;
    }

    /** Inventory value using weighted-average cost. */
    public function stockValue(): string
    {
        return bcmul((string) $this->current_stock, (string) $this->average_cost, 2);
    }

    public function hasTransactionHistory(): bool
    {
        return $this->stockMovements()->exists() || $this->purchaseItems()->exists();
    }
}
