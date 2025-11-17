<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;             
use App\Http\Requests\StoreMenuRequest;          
use App\Http\Requests\UpdateMenuRequest; 
use App\Models\Menu;
use App\Services\MenuService;
use App\Http\Resources\MenuResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Carbon\CarbonPeriod; 
class MenuController extends Controller
{
    protected $menuService;

    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;

    }

    /**
     * Afficher la liste des menus
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $menus = $this->menuService->getAllMenus($request->all());
            
            return response()->json([
                'success' => true,
                'data' => MenuResource::collection($menus),
                'message' => 'Menus récupérés avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des menus',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer un nouveau menu
     */
public function storeDailyMenu(StoreMenuRequest $request): \Illuminate\Http\JsonResponse
{
    try {
        $menus = $this->menuService->upsertDailyMenus(
            $request->input('date_menu'),
            $request->input('type'),
            $request->input('lunch'),
            $request->input('snack')
        );

        return response()->json([
            'success' => true,
            'data'    => MenuResource::collection($menus),
            'message' => 'Menu du jour enregistré',
        ], 201);

    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de l’enregistrement du menu du jour',
            'error'   => $e->getMessage(),
        ], 500);
    }
}    /**
     * Afficher un menu spécifique
     */
    public function show($id): JsonResponse
    {
        try {
            $menu = $this->menuService->getMenuById($id);
            
            if (!$menu) {
                return response()->json([
                    'success' => false,
                    'message' => 'Menu non trouvé'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => new MenuResource($menu),
                'message' => 'Menu récupéré avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du menu',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour un menu
     */
public function update(UpdateMenuRequest $request, $id): JsonResponse
{
    $menu = $this->menuService->updateMenu($id, $request->validated());
    if (!$menu) {
        return response()->json(['success' => false, 'message' => 'Menu non trouvé'], 404);
    }
    return response()->json([
        'success' => true,
        'data' => new MenuResource($menu),
        'message' => 'Menu mis à jour avec succès'
    ]);
}

    /**
     * Supprimer un menu
     */
    public function destroy($id): JsonResponse
    {
        try {
            $deleted = $this->menuService->deleteMenu($id);
            
            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Menu non trouvé'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Menu supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du menu',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer un menu pour toute une semaine
     */
    public function createWeeklyMenu(Request $request): JsonResponse
    {
        $request->validate([
            'week_start_date' => 'required|date',
            'type_repas' => 'required|in:lunch,snack',
            'menus' => 'required|array|size:5', // Lundi à Vendredi
            'menus.*.description' => 'required|string|max:1000',
            'menus.*.ingredients' => 'required|string|max:2000'
        ]);

        try {
            $weeklyMenus = $this->menuService->createWeeklyMenu(
                $request->week_start_date,
                $request->type_repas,
                $request->menus
            );
            
            return response()->json([
                'success' => true,
                'data' => MenuResource::collection($weeklyMenus),
                'message' => 'Menu hebdomadaire créé avec succès'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du menu hebdomadaire',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir le menu de la semaine courante
     */
public function getCurrentWeekMenu(Request $request): JsonResponse
    {
        // optionnel : filtre par type si fourni
        $type = $request->query('type_repas');
        if ($type && !in_array($type, ['lunch', 'snack'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Paramètre type_repas invalide (attendu: lunch|snack)',
                'errors'  => ['type_repas' => ['The type repas field is invalid.']],
            ], 422);
        }

        try {
            // Lundi → Dimanche (semaine courante)
            $start = Carbon::now()->startOfWeek(Carbon::MONDAY);
            $end   = (clone $start)->endOfWeek(Carbon::SUNDAY);

            $rows = Menu::query()
                ->whereBetween('date_menu', [$start->toDateString(), $end->toDateString()])
                ->when($type, fn ($q) => $q->where('type_repas', $type))
                ->orderBy('date_menu')
                ->get(['id', 'date_menu', 'type_repas', 'description', 'ingredients']);

            // Si on a demandé un seul type → liste plate
            if ($type) {
                return response()->json([
                    'success' => true,
                    'data'    => $rows,
                    'message' => "Menus de la semaine ($type) récupérés avec succès",
                ]);
            }

            // Clé de regroupement toujours au format 'Y-m-d'
            $byDate = $rows->groupBy(function ($m) {
                // $m->date_menu peut être Carbon (cast) ou string
                return $m->date_menu instanceof Carbon
                    ? $m->date_menu->toDateString()
                    : Carbon::parse($m->date_menu)->toDateString();
            });

            $period = CarbonPeriod::create($start, $end);
            $out = [];

            foreach ($period as $day) {
                $date = $day->toDateString();
                /** @var \Illuminate\Support\Collection $list */
                $list = $byDate->get($date, collect());

                $l = $list->firstWhere('type_repas', 'lunch');
                $s = $list->firstWhere('type_repas', 'snack');

                $out[] = [
                    'date_menu' => $date,
                    'lunch' => $l ? [
                        'id'          => $l->id,
                        'description' => $l->description,
                        'ingredients' => $l->ingredients,
                    ] : null,
                    'snack' => $s ? [
                        'id'          => $s->id,
                        'description' => $s->description,
                        'ingredients' => $s->ingredients,
                    ] : null,
                ];
            }

            return response()->json([
                'success' => true,
                'data'    => $out,
                'message' => 'Menus (lunch + snack) de la semaine courante.',
            ]);
        } catch (\Throwable $e) {
            \Log::error('[menus.weekly.current] '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du menu de la semaine',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Obtenir le menu d'une semaine spécifique
     */
    public function getWeekMenu(Request $request): JsonResponse
    {
        $request->validate([
            'week_start_date' => 'required|date',
            'type_repas' => 'required|in:lunch,snack'
        ]);

        try {
            $menus = $this->menuService->getWeekMenu(
                $request->week_start_date,
                $request->type_repas
            );
            
            return response()->json([
                'success' => true,
                'data' => MenuResource::collection($menus),
                'message' => 'Menu de la semaine récupéré avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du menu de la semaine',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dupliquer un menu hebdomadaire vers une autre semaine
     */
    public function duplicateWeeklyMenu(Request $request): JsonResponse
    {
        $request->validate([
            'source_week_start' => 'required|date',
            'target_week_start' => 'required|date|after:source_week_start',
            'type_repas' => 'required|in:lunch,snack'
        ]);

        try {
            $duplicatedMenus = $this->menuService->duplicateWeeklyMenu(
                $request->source_week_start,
                $request->target_week_start,
                $request->type_repas
            );
            
            return response()->json([
                'success' => true,
                'data' => MenuResource::collection($duplicatedMenus),
                'message' => 'Menu hebdomadaire dupliqué avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la duplication du menu hebdomadaire',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}