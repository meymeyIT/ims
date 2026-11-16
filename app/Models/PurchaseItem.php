<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseItem extends Model
{
    /** quantity_received is updated only by the receiving service. */
    protected $attributes = ['quantity_received' => '0.000'];

    protected $fillable = [
        'purchase_id',
        'product_id',
        'quantity_ordered',
        'unit_cost',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:3',
            'quantity_received' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'subtotal' => 'decimal:2',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }

    public function remainingQuantity(): string
    {
        return bcsub((string) $this->quantity_ordered, (string) $this->quantity_received, 3);
    }

    public function isFullyReceived(): bool
    {
        return bccomp($this->remainingQuantity(), '0', 3) <= 0;
    }
}
