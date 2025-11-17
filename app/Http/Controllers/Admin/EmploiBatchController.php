<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BatchEmploiGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;

class EmploiBatchController extends Controller
{
    public function __construct(private BatchEmploiGeneratorService $svc) {}

    public function generateAll(Request $r)
    {
        $data = $r->validate([
            'classe_ids'     => 'array|nullable',
            'classe_ids.*'   => 'integer|exists:classe,id',   // <-- 'classe'
            'period_start'   => 'required|date',
            'period_end'     => 'required|date|after:period_start',
            'effective_from' => 'required|date|after_or_equal:period_start',
        ]);

        $res = $this->svc->generateAll(
            $data['classe_ids'] ?? null,
            $data['period_start'],
            $data['period_end'],
            $data['effective_from']
        );

        return response()->json(['success' => true] + $res);
    }
}
