<?php

namespace App\Modules\Emr\Models;

use App\Modules\Identity\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Catatan koreksi atas SOAP yang sudah dikunci. Append-only. */
class ClinicalNoteAddendum extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'clinical_note_addenda';

    public const UPDATED_AT = null;

    protected $fillable = ['clinical_note_id', 'author_id', 'content'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Addendum tidak boleh diubah.'));
        static::deleting(fn () => throw new LogicException('Addendum tidak boleh dihapus.'));
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
