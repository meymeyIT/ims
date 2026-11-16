<?php

namespace App\Enums;

enum StockOutReason: string
{
    case Sold = 'sold';
    case Damaged = 'damaged';
    case Expired = 'expired';
    case InternalUsage = 'internal_usage';
    case ReturnedToSupplier = 'returned_to_supplier';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Sold => 'Sold',
            self::Damaged => 'Damaged',
            self::Expired => 'Expired',
            self::InternalUsage => 'Internal Usage',
            self::ReturnedToSupplier => 'Returned to Supplier',
            self::Other => 'Other',
        };
    }
}
