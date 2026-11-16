<?php

namespace App\Enums;

enum MovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Adjustment = 'adjustment';   // signed: can increase or decrease
    case ReturnIn = 'return_in';      // e.g. customer returns goods
    case ReturnOut = 'return_out';    // e.g. goods returned to supplier

    public function label(): string
    {
        return match ($this) {
            self::In => 'IN',
            self::Out => 'OUT',
            self::Adjustment => 'ADJUSTMENT',
            self::ReturnIn => 'RETURN_IN',
            self::ReturnOut => 'RETURN_OUT',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::In, self::ReturnIn => 'success',
            self::Out, self::ReturnOut => 'danger',
            self::Adjustment => 'warning',
        };
    }
}
