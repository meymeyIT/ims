<?php

namespace App\Enums;

enum PurchaseStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::PartiallyReceived => 'Partially Received',
            self::Received => 'Received',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Bootstrap 5 badge colour for the UI. */
    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Pending => 'warning',
            self::Approved => 'primary',
            self::PartiallyReceived => 'info',
            self::Received => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function canBeApproved(): bool
    {
        return in_array($this, [self::Draft, self::Pending], true);
    }

    public function canBeReceived(): bool
    {
        return in_array($this, [self::Approved, self::PartiallyReceived], true);
    }

    /** Once anything has been received, the PO can no longer be cancelled. */
    public function canBeCancelled(): bool
    {
        return in_array($this, [self::Draft, self::Pending, self::Approved], true);
    }
}
