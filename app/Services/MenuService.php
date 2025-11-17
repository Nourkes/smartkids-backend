<?php

namespace App\Services;

use App\Models\Menu;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class MenuService
{
public function upsertDailyMenus(string $date, string $type, ?array $lunch, ?array $snack): Collection
{
    $out = new Collection(); // <- au lieu de collect()
    $day = \Carbon\Carbon::parse($date)->toDateString();

    $save = function (string $t, array $payload) use ($day) {
        return \App\Models\Menu::updateOrCreate(
            ['date_menu' => $day, 'type_repas' => $t],
            [
                'description' => trim($payload['description']),
                'ingredients' => trim($payload['ingredients']),
            ]
        );
    };

    if (in_array($type, ['lunch', 'both']) && $lunch) {
        $out->push($save('lunch', $lunch));
    }
    if (in_array($type, ['snack', 'both']) && $snack) {
        $out->push($save('snack', $snack));
    }

    return $out;
}
    /**
     * Récupérer tous les menus avec filtres
     */
    public function getAllMenus(array $filters = []): LengthAwarePaginator
    {
        $query = Menu::query();

        // Filtre par type de repas
        if (isset($filters['type_repas']) && in_array($filters['type_repas'], ['lunch', 'snack'])) {
            $query->where('type_repas', $filters['type_repas']);
        }

        // Filtre par date
        if (isset($filters['date_menu'])) {
            $query->whereDate('date_menu', $filters['date_menu']);
        }

        // Filtre par période
        if (isset($filters['date_start']) && isset($filters['date_end'])) {
            $query->whereBetween('date_menu', [$filters['date_start'], $filters['date_end']]);
        }

        // Filtre par semaine courante
        if (isset($filters['current_week']) && $filters['current_week']) {
            $startOfWeek = Carbon::now()->startOfWeek();
            $endOfWeek = Carbon::now()->endOfWeek();
            $query->whereBetween('date_menu', [$startOfWeek, $endOfWeek]);
        }

        // Recherche dans la description ou les ingrédients
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('description', 'LIKE', "%{$search}%")
                  ->orWhere('ingredients', 'LIKE', "%{$search}%");
            });
        }

        // Tri
        $sortBy = $filters['sort_by'] ?? 'date_menu';
        $sortOrder = $filters['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $filters['per_page'] ?? 15;
        return $query->paginate($perPage);
    }

    /**
     * Créer un nouveau menu
     */
    public function createMenu(array $data): Menu
    {
        return Menu::create([
            'description' => $data['description'],
            'date_menu' => $data['date_menu'],
            'type_repas' => $data['type_repas'],
            'ingredients' => $data['ingredients']
        ]);
    }

    /**
     * Récupérer un menu par ID
     */
    public function getMenuById($id): ?Menu
    {
        return Menu::find($id);
    }

    /**
     * Mettre à jour un menu
     */
    public function updateMenu($id, array $data): ?Menu
    {
        $menu = Menu::find($id);
        
        if (!$menu) {
            return null;
        }

        $menu->update([
            'description' => $data['description'] ?? $menu->description,
            'date_menu' => $data['date_menu'] ?? $menu->date_menu,
            'type_repas' => $data['type_repas'] ?? $menu->type_repas,
            'ingredients' => $data['ingredients'] ?? $menu->ingredients
        ]);

        return $menu->fresh();
    }

    /**
     * Supprimer un menu
     */
    public function deleteMenu($id): bool
    {
        $menu = Menu::find($id);
        
        if (!$menu) {
            return false;
        }

        return $menu->delete();
    }

    /**
     * Créer un menu pour toute une semaine
     */
    public function createWeeklyMenu(string $weekStartDate, string $typeRepas, array $menusData): Collection
    {
        $startDate = Carbon::parse($weekStartDate)->startOfWeek();
        $createdMenus = collect();

        $existingMenus = Menu::whereBetween('date_menu', [
            $startDate->copy(),
            $startDate->copy()->endOfWeek()
        ])->where('type_repas', $typeRepas)->exists();

        if ($existingMenus) {
            throw new \Exception("Des menus existent déjà pour cette semaine et ce type de repas.");
        }

        for ($i = 0; $i < 5; $i++) {
            $menuDate = $startDate->copy()->addDays($i);
            $menuData = $menusData[$i] ?? null;

            if ($menuData) {
                $menu = Menu::create([
                    'description' => $menuData['description'],
                    'date_menu' => $menuDate->format('Y-m-d'),
                    'type_repas' => $typeRepas,
                    'ingredients' => $menuData['ingredients']
                ]);

                $createdMenus->push($menu);
            }
        }

        return $createdMenus;
    }

 
    public function getCurrentWeekMenu(string $typeRepas): Collection
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        return Menu::whereBetween('date_menu', [$startOfWeek, $endOfWeek])
                   ->where('type_repas', $typeRepas)
                   ->orderBy('date_menu')
                   ->get();
    }

    /**
     * Obtenir le menu d'une semaine spécifique
     */
    public function getWeekMenu(string $weekStartDate, string $typeRepas): Collection
    {
        $startDate = Carbon::parse($weekStartDate)->startOfWeek();
        $endDate = $startDate->copy()->endOfWeek();

        return Menu::whereBetween('date_menu', [$startDate, $endDate])
                   ->where('type_repas', $typeRepas)
                   ->orderBy('date_menu')
                   ->get();
    }

  
    public function duplicateWeeklyMenu(string $sourceWeekStart, string $targetWeekStart, string $typeRepas): Collection
    {
        $sourceStart = Carbon::parse($sourceWeekStart)->startOfWeek();
        $sourceEnd = $sourceStart->copy()->endOfWeek();
        $targetStart = Carbon::parse($targetWeekStart)->startOfWeek();

        // Récupérer les menus source
        $sourceMenus = Menu::whereBetween('date_menu', [$sourceStart, $sourceEnd])
                          ->where('type_repas', $typeRepas)
                          ->orderBy('date_menu')
                          ->get();

        if ($sourceMenus->isEmpty()) {
            throw new \Exception("Aucun menu trouvé pour la semaine source.");
        }

        // Vérifier si des menus existent déjà pour la semaine cible
        $existingTargetMenus = Menu::whereBetween('date_menu', [
            $targetStart->copy(),
            $targetStart->copy()->endOfWeek()
        ])->where('type_repas', $typeRepas)->exists();

        if ($existingTargetMenus) {
            throw new \Exception("Des menus existent déjà pour la semaine cible.");
        }

        $duplicatedMenus = collect();

        foreach ($sourceMenus as $index => $sourceMenu) {
            $targetDate = $targetStart->copy()->addDays($index);
            
            $duplicatedMenu = Menu::create([
                'description' => $sourceMenu->description,
                'date_menu' => $targetDate->format('Y-m-d'),
                'type_repas' => $sourceMenu->type_repas,
                'ingredients' => $sourceMenu->ingredients
            ]);

            $duplicatedMenus->push($duplicatedMenu);
        }

        return $duplicatedMenus;
    }

    /**
     * Obtenir les statistiques des menus
     */
    public function getMenuStatistics(): array
    {
        $currentWeekStart = Carbon::now()->startOfWeek();
        $currentWeekEnd = Carbon::now()->endOfWeek();

        return [
            'total_menus' => Menu::count(),
            'current_week_lunch_menus' => Menu::whereBetween('date_menu', [$currentWeekStart, $currentWeekEnd])
                                              ->where('type_repas', 'lunch')
                                              ->count(),
            'current_week_snack_menus' => Menu::whereBetween('date_menu', [$currentWeekStart, $currentWeekEnd])
                                              ->where('type_repas', 'snack')
                                              ->count(),
            'this_month_menus' => Menu::whereMonth('date_menu', Carbon::now()->month)
                                     ->whereYear('date_menu', Carbon::now()->year)
                                     ->count(),
            'menus_by_type' => Menu::selectRaw('type_repas, COUNT(*) as count')
                                  ->groupBy('type_repas')
                                  ->pluck('count', 'type_repas')
                                  ->toArray()
        ];
    }

    /**
     * Vérifier si un menu existe pour une date et un type donnés
     */
    public function menuExists(string $date, string $typeRepas): bool
    {
        return Menu::where('date_menu', $date)
                  ->where('type_repas', $typeRepas)
                  ->exists();
    }

    /**
     * Obtenir les prochains menus
     */
    public function getUpcomingMenus(int $days = 7): Collection
    {
        $startDate = Carbon::now();
        $endDate = Carbon::now()->addDays($days);

        return Menu::whereBetween('date_menu', [$startDate, $endDate])
                   ->orderBy('date_menu')
                   ->orderBy('type_repas')
                   ->get();
    }
}