<?php

namespace App\Modules\Inventory\Enums;

enum StockMovementType: string
{
    case Receipt = 'receipt';
    case Adjustment = 'adjustment';
    case Opname = 'opname';
    case Dispense = 'dispense';
    case Return = 'return';
    case Quarantine = 'quarantine';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Penerimaan',
            self::Adjustment => 'Penyesuaian',
            self::Opname => 'Stok opname',
            self::Dispense => 'Pengeluaran',
            self::Return => 'Retur',
            self::Quarantine => 'Karantina',
            self::Void => 'Void transaksi',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Receipt, self::Return, self::Void => 'badge-soft-success',
            self::Dispense => 'badge-soft-primary',
            self::Adjustment, self::Opname => 'badge-soft-warning',
            self::Quarantine => 'badge-soft-danger',
        };
    }
}
