<?php

namespace App\Modules\Pos\Exceptions;

use DomainException;

/** Aksi kasir/farmasi yang melanggar aturan (keranjang sudah dibayar, resep belum diverifikasi, dst.). */
class PosActionNotAllowed extends DomainException {}
