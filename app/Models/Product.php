<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory, SoftDeletes;

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

    /** in_stock: above minimum · low_stock: 0 < stock <= minimum · out_of_stock: 0 */
    public function scopeStockStatus(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            'out_of_stock' => $query->where('current_stock', '<=', 0),
            'low_stock' => $query->where('current_stock', '>', 0)->whereColumn('current_stock', '<=', 'minimum_stock'),
            'in_stock' => $query->whereColumn('current_stock', '>', 'minimum_stock'),
            default => $query,
        };
    }

    // ---------------------------------------------------------------- helpers

    public function stockStatus(): string
    {
        if (bccomp((string) $this->current_stock, '0', 3) <= 0) {
            return 'out_of_stock';
        }

        return $this->isLowStock() ? 'low_stock' : 'in_stock';
    }

    /**
     * Suggested order quantity for a low product: enough to reach TWICE the minimum stock
     * (simple rule; documented in the README). Null when no minimum is set.
     */
    public function suggestedRestockQuantity(): ?string
    {
        if (bccomp((string) $this->minimum_stock, '0', 3) <= 0) {
            return null;
        }

        $target = bcmul((string) $this->minimum_stock, '2', 3);
        $needed = bcsub($target, (string) $this->current_stock, 3);

        return bccomp($needed, '0', 3) > 0 ? $needed : null;
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

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
