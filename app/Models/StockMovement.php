<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Immutable inventory ledger entry.
 *
 * Rows can only be inserted. Any attempt to update or delete one through
 * Eloquent throws, so history stays auditable. Mistakes are corrected with a
 * compensating movement (reversal_of_id), never by editing the original.
 */
class StockMovement extends Model
{
    protected $fillable = [
        'movement_number',
        'product_id',
        'user_id',
        'supplier_id',
        'type',
        'quantity',
        'previous_stock',
        'new_stock',
        'unit_cost',
        'reference_type',
        'reference_id',
        'reason',
        'notes',
        'reversal_of_id',
        'movement_date',
    ];

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'quantity' => 'decimal:3',
            'previous_stock' => 'decimal:3',
            'new_stock' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'movement_date' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Stock movements are immutable. Create a reversal instead.');
        });

        static::deleting(function () {
            throw new LogicException('Stock movements cannot be deleted. Create a reversal instead.');
        });
    }

    // ---------------------------------------------------------------- relationships
    // withTrashed() so history still shows archived products / deleted users.

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /** Source document line, e.g. a PurchaseReceiptItem. */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    public function isIncrease(): bool
    {
        return bccomp((string) $this->quantity, '0', 3) > 0;
    }
}
