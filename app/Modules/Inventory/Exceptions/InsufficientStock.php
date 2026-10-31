<?php

namespace App\Modules\Inventory\Exceptions;

use DomainException;

/** Saldo batch tidak cukup / akan menjadi negatif. */
class InsufficientStock extends DomainException {}
