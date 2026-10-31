<?php

namespace App\Modules\Import\Jobs;

use App\Modules\Import\Models\Import;
use App\Modules\Import\Services\ImportManager;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Simpan baris valid sebuah impor; berjalan di konteks faskes pemilik impor. */
class ProcessImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $importId) {}

    public function handle(ImportManager $manager, TenantContext $context): void
    {
        $import = Import::withoutGlobalScopes()->with('user')->find($this->importId);
        if (! $import || $import->status !== 'queued') {
            return;
        }

        $context->runAs($import->tenant_id, fn () => $manager->process($import));
    }
}
