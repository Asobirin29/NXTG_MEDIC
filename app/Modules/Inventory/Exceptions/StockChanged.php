<?php

namespace App\Modules\Inventory\Exceptions;

use DomainException;

/** Saldo berubah sejak form dibuka (mis. ada pengeluaran saat opname berlangsung). */
class StockChanged extends DomainException {}
