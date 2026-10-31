<?php

namespace App\Modules\Emr\Exceptions;

use DomainException;

/** Aksi RME yang tidak sah untuk status kunjungan saat ini (pesan ditampilkan ke pengguna). */
class EmrActionNotAllowed extends DomainException {}
