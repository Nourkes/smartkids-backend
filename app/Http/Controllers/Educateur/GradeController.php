<?php
// app/Http/Controllers/Educateur/GradeController.php
namespace App\Http\Controllers\Educateur;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Enfant;
use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GradeController extends Controller
{
    // Roster + notes actuelles pour une classe/matière/semestre
    // GET /api/educateur/grades/roster?classe_id=..&matiere_id=..&term=1&year=2024–2025
    public function roster(Request $req)
    {
        $req->validate([
            'classe_id'  => 'required|exists:classe,id',
            'term'       => 'required|in:1,2',
            'year'       => 'required|string',
            'matiere_id' => 'nullable|exists:matiere,id',
        ]);

        $classeId  = (int)$req->classe_id;
        $matiereId = $req->matiere_id;
        $term      = (int)$req->term;
        $year      = $req->year;

        // élèves de la classe
        $students = Enfant::where('classe_id', $classeId)
    ->select('id', 'nom', 'prenom')
    ->orderBy('nom')->orderBy('prenom')
    ->get()
    ->map(function ($e) {
        return [
            'enfant_id'   => $e->id,
            'nom_complet' => trim(($e->nom ?? '').' '.($e->prenom ?? '')),
        ];
    });
        // notes existantes
        $existing = Grade::where([
                'classe_id'   => $classeId,
                'school_year' => $year,
                'term'        => $term,
            ])
            ->when($matiereId, fn($q) => $q->where('matiere_id', $matiereId))
            ->get()
            ->keyBy('enfant_id');

       $data = $students->map(function ($s) use ($existing) {
    $g = $existing->get($s['enfant_id']);
    return [
        'enfant_id'   => $s['enfant_id'],
        'nom_complet' => $s['nom_complet'],
        'grade'       => $g->grade  ?? null,
        'remark'      => $g->remark ?? null,
    ];
});

        return response()->json([
            'success' => true,
            'data' => [
                'students' => $data,
            ],
        ]);
    }

    // Upsert en lot
    // POST /api/educateur/grades/bulk
    // {
    //   "classe_id": 3, "matiere_id": 5, "term":1, "year":"2024–2025",
    //   "grades":[ {"enfant_id":10,"grade":"A"}, ... ],
    //   "remarks":{"10":"Très bien", "12":"..."} // optionnel
    // }
    public function bulkUpsert(Request $req)
    {
        $req->validate([
            'classe_id'  => 'required|exists:classe,id',
            'term'       => 'required|in:1,2',
            'year'       => 'required|string',
            'matiere_id' => 'nullable|exists:matiere,id',
            'grades'     => 'required|array|min:1',
            'grades.*.enfant_id' => 'required|exists:enfant,id',
            'grades.*.grade'     => 'required|string|max:2',
            'remarks'    => 'nullable|array',
        ]);

        $user = $req->user(); // éducateur connecté
        $classeId  = (int)$req->classe_id;
        $matiereId = $req->matiere_id;
        $term      = (int)$req->term;
        $year      = $req->year;
        $remarks   = $req->input('remarks', []);

        DB::transaction(function () use ($req, $user, $classeId, $matiereId, $term, $year, $remarks) {
            foreach ($req->grades as $line) {
                $enfantId = (int)$line['enfant_id'];
                $gradeVal = (string)$line['grade'];

                Grade::updateOrCreate(
                    [
                        'enfant_id'   => $enfantId,
                        'matiere_id'  => $matiereId,
                        'school_year' => $year,
                        'term'        => $term,
                    ],
                    [
                        'classe_id'   => $classeId,
                        'teacher_id'  => $user->id,
                        'grade'       => $gradeVal,
                        'remark'      => $remarks[$enfantId] ?? null,
                    ]
                );
            }
        });

        return response()->json(['success' => true, 'message' => 'Notes enregistrées.']);
    }
}
