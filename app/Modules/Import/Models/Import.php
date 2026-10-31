<?php

namespace App\Modules\Import\Models;

use App\Modules\Identity\Models\User;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Import extends Model
{
    use BelongsToTenant;

    protected $fillable = ['user_id', 'type', 'status', 'original_name', 'path'];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'preview' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'total_rows' => 'integer',
            'valid_rows' => 'integer',
            'error_rows' => 'integer',
            'imported_rows' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed'], true);
    }

    public function statusLabel(): string
    {
        return [
            'previewed' => 'Menunggu konfirmasi',
            'queued' => 'Dalam antrean',
            'processing' => 'Diproses',
            'completed' => 'Selesai',
            'failed' => 'Gagal',
        ][$this->status];
    }

    public function statusBadge(): string
    {
        return [
            'previewed' => 'badge-soft-warning',
            'queued' => 'badge-soft-info',
            'processing' => 'badge-soft-info',
            'completed' => 'badge-soft-success',
            'failed' => 'badge-soft-danger',
        ][$this->status];
    }
}
