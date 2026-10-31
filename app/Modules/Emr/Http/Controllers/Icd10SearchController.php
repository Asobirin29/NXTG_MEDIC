<?php

namespace App\Modules\Emr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Emr\Models\Icd10;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pencarian cepat ICD-10 untuk form diagnosis (dipakai layar RME di S3). */
class Icd10SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = $request->string('q')->trim()->value();

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        return response()->json(
            Icd10::search($term)->limit(20)->get(['id', 'code', 'name_id', 'name_en']),
        );
    }
}
