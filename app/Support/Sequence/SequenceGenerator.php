<?php

namespace App\Support\Sequence;

use Illuminate\Support\Facades\DB;

/**
 * Nomor berurutan per faskes yang aman dari balapan (dua loket mendaftar
 * bersamaan): baris counter dikunci FOR UPDATE di dalam transaksi.
 */
class SequenceGenerator
{
    public function next(int $tenantId, string $name): int
    {
        return DB::transaction(function () use ($tenantId, $name) {
            DB::table('sequences')->insertOrIgnore(['tenant_id' => $tenantId, 'name' => $name, 'value' => 0]);

            $current = DB::table('sequences')
                ->where('tenant_id', $tenantId)
                ->where('name', $name)
                ->lockForUpdate()
                ->value('value');

            $next = $current + 1;

            DB::table('sequences')->where('tenant_id', $tenantId)->where('name', $name)->update(['value' => $next]);

            return $next;
        });
    }
}
