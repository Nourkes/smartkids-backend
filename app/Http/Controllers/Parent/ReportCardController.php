<?php
// app/Http/Controllers/Parent/ReportCardController.php
namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Matiere;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportCardController extends Controller
{
    public function show(Request $req, int $enfantId)
    {
        $req->validate([
            'semester' => 'required|in:1,2',
            'year'     => 'nullable|string',
        ]);

        $term = (int)$req->semester;
        $year = $req->input('year') ?: $this->guessYear();

        $rows = Grade::with('matiere:id,nom')
            ->where('enfant_id', $enfantId)
            ->where('term', $term)
            ->where('school_year', $year)
            ->get();

        $grades = [];
        foreach ($rows as $g) {
            $subject = $g->matiere?->nom ?? 'General';
            $grades[$subject] = $g->grade;
        }

        $remarks = optional($rows->firstWhere('remark', '!=', null))->remark;

        return response()->json([
            'success' => true,
            'data' => [
                'year'    => $year,
                'term'    => $term,
                'grades'  => $grades,  
                'remarks' => $remarks,
            ],
        ]);
    }

    private function guessYear(): string {
        $now = now();
        $start = $now->month >= 9 ? $now->year : $now->year - 1;
        return $start.'–'.($start+1);
    }
}
