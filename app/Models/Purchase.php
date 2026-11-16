<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    /**
     * Status/approval/cancellation columns are not fillable: they are changed
     * only by PurchaseService (Phase 5) through explicit state transitions.
     */
    protected $attributes = [
        'status' => 'draft',
        'subtotal' => '0.00',
        'discount' => '0.00',
        'tax' => '0.00',
        'total_amount' => '0.00',
    ];

    protected $fillable = [
        'purchase_number',
        'supplier_id',
        'created_by',
        'purchase_date',
        'subtotal',
        'discount',
        'tax',
        'total_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseStatus::class,
            'purchase_date' => 'date',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseReceipt::class);
    }
}
