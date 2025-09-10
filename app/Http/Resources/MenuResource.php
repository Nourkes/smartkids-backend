<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class MenuResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'date_menu' => $this->date_menu,
            'date_menu_formatted' => Carbon::parse($this->date_menu)->format('d/m/Y'),
            'day_of_week' => Carbon::parse($this->date_menu)->locale('fr')->dayName,
            'day_of_week_short' => Carbon::parse($this->date_menu)->locale('fr')->shortDayName,
            'type_repas' => $this->type_repas,
            'type_repas_label' => $this->getTypeRepasLabel(),
            'ingredients' => $this->ingredients,
            'ingredients_list' => $this->getIngredientsList(),
            'is_today' => Carbon::parse($this->date_menu)->isToday(),
            'is_past' => Carbon::parse($this->date_menu)->isPast(),
            'is_future' => Carbon::parse($this->date_menu)->isFuture(),
            'week_number' => Carbon::parse($this->date_menu)->weekOfYear,
            'month' => Carbon::parse($this->date_menu)->locale('fr')->monthName,
            'created_at' => $this->created_at,
            'created_at_formatted' => $this->created_at ? $this->created_at->format('d/m/Y H:i') : null,
            'updated_at' => $this->updated_at,
            'updated_at_formatted' => $this->updated_at ? $this->updated_at->format('d/m/Y H:i') : null,
            
            // Informations supplémentaires pour l'affichage
            'display' => [
                'title' => $this->getDisplayTitle(),
                'subtitle' => $this->getDisplaySubtitle(),
                'badge_color' => $this->getBadgeColor(),
                'icon' => $this->getTypeIcon(),
            ],
            
            // Métadonnées utiles
            'meta' => [
                'ingredients_count' => $this->getIngredientsCount(),
                'description_length' => strlen($this->description),
                'is_weekend' => Carbon::parse($this->date_menu)->isWeekend(),
                'days_until' => Carbon::now()->diffInDays(Carbon::parse($this->date_menu), false),
                'relative_date' => Carbon::parse($this->date_menu)->locale('fr')->diffForHumans(),
            ]
        ];
    }

    /**
     * Obtenir le libellé du type de repas
     */
    private function getTypeRepasLabel(): string
    {
        return match($this->type_repas) {
            'lunch' => 'Déjeuner',
            'snack' => 'Goûter',
            default => ucfirst($this->type_repas)
        };
    }

    /**
     * Obtenir la liste des ingrédients sous forme de tableau
     */
    private function getIngredientsList(): array
    {
        if (empty($this->ingredients)) {
            return [];
        }

        // Diviser par virgules ou retours à la ligne
        $ingredients = preg_split('/[,\n\r]+/', $this->ingredients);
        
        // Nettoyer et filtrer les ingrédients
        return array_filter(array_map('trim', $ingredients), function($ingredient) {
            return !empty($ingredient);
        });
    }

    /**
     * Obtenir le nombre d'ingrédients
     */
    private function getIngredientsCount(): int
    {
        return count($this->getIngredientsList());
    }

    /**
     * Obtenir le titre d'affichage
     */
    private function getDisplayTitle(): string
    {
        $dayName = Carbon::parse($this->date_menu)->locale('fr')->dayName;
        $typeLabel = $this->getTypeRepasLabel();
        
        return "{$typeLabel} - {$dayName}";
    }

    /**
     * Obtenir le sous-titre d'affichage
     */
    private function getDisplaySubtitle(): string
    {
        $date = Carbon::parse($this->date_menu)->format('d/m/Y');
        $ingredientsCount = $this->getIngredientsCount();
        
        return "{$date} • {$ingredientsCount} ingrédient" . ($ingredientsCount > 1 ? 's' : '');
    }

    /**
     * Obtenir la couleur du badge selon le type de repas
     */
    private function getBadgeColor(): string
    {
        return match($this->type_repas) {
            'lunch' => 'bg-blue-100 text-blue-800',
            'snack' => 'bg-orange-100 text-orange-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    /**
     * Obtenir l'icône selon le type de repas
     */
    private function getTypeIcon(): string
    {
        return match($this->type_repas) {
            'lunch' => '🍽️',
            'snack' => '🍪',
            default => '🍴'
        };
    }

    /**
     * Ressources supplémentaires pour certains contextes
     */
    public function with($request)
    {
        return [
            'meta' => [
                'version' => '1.0',
                'generated_at' => now()->toISOString(),
            ]
        ];
    }

    /**
     * Personnaliser la réponse pour différents contextes
     */
    public static function collection($resource)
    {
        return parent::collection($resource)->additional([
            'meta' => [
                'total' => $resource instanceof \Illuminate\Pagination\LengthAwarePaginator ? $resource->total() : $resource->count(),
                'generated_at' => now()->toISOString(),
                'context' => 'menu_collection'
            ]
        ]);
    }
}   