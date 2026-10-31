<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Drug;
use App\Modules\Inventory\Services\PackagingConverter;
use App\Modules\Inventory\Services\StockLevels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pencarian obat aktif untuk e-Resep & kasir: komposisi, golongan, kemasan, dan sisa stok. */
class DrugSearchController extends Controller
{
    public function __invoke(Request $request, StockLevels $stock, PackagingConverter $converter): JsonResponse
    {
        $term = $request->string('q')->trim()->value();
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $drugs = Drug::with(['substances', 'packagings'])->active()->search($term)->orderBy('name')->limit(20)->get();
        $levels = $stock->available($drugs->pluck('id')->all());

        return response()->json($drugs->map(function (Drug $d) use ($levels, $converter) {
            $packs = $d->packagings->map(fn ($p) => ['unit_name' => $p->unit_name, 'quantity_in_base' => $p->quantity_in_base])->all();
            $available = $levels[$d->id] ?? null;

            return [
                'id' => $d->id,
                'name' => $d->name,
                'composition' => $d->compositionLabel(),
                'category' => $d->category->value,
                'category_label' => $d->category->label(),
                'category_badge' => $d->category->badge(),
                'base_unit' => $d->base_unit,
                'stock' => $available,
                'stock_label' => $available === null ? null : $converter->format($available, $packs, $d->base_unit),
                'packagings' => $d->packagings->map(fn ($p) => [
                    'id' => $p->id, 'unit_name' => $p->unit_name, 'quantity_in_base' => $p->quantity_in_base, 'is_default' => $p->is_default_sale,
                ])->values(),
            ];
        }));
    }
}
