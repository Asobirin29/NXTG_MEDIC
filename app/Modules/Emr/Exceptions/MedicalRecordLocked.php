<?php

namespace App\Modules\Emr\Exceptions;

use DomainException;

class MedicalRecordLocked extends DomainException
{
    public function __construct(string $message = 'Rekam medis sudah dikunci. Koreksi hanya melalui addendum.')
    {
        parent::__construct($message);
    }
}
