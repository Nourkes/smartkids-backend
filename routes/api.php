<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RoleController;
use App\Http\Controllers\Admin\ParentController;
use App\Http\Controllers\Admin\EnfantController;
use App\Http\Controllers\Admin\ClasseController; // <= NEW
use App\Http\Controllers\PresenceController;
use App\Http\Controllers\Admin\ActiviteController; 
use App\Http\Controllers\Admin\EducateurController;
Route::get('/test', fn () => response()->json(['message' => 'Hello depuis Laravel']));
Route::get('/activites/types', [ActiviteController::class, 'typesPublic']);
// ──────────────── ROUTES PUBLIQUES ────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});
Route::get('/activites/types', [ActiviteController::class, 'typesPublic']);
Route::get('/admin/educateurs/{id}', [EducateurController::class, 'show']);
// ──────────────── ROUTES PROTÉGÉES ────────────────
Route::middleware('auth:sanctum')->group(function () {

    // ─── Authentification ───
    Route::prefix('auth')->group(function () {
        Route::post('/logout',        [AuthController::class, 'logout']);
        Route::post('/logout-all',    [AuthController::class, 'logoutAll']);
        Route::get('/me',             [AuthController::class, 'me']);
        Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
    });

    // ─── Admin ───
    Route::prefix('admin')->middleware('role:admin')->group(function () {
            Route::get('activites/types', [ActiviteController::class, 'types'])
            ->name('admin.activites.types');
        Route::get('/activites/stats', [ActiviteController::class, 'stats']);
        Route::get('/menus',        [MenuController::class, 'index'])->name('menus.index');
    Route::get('/menus/{menu}', [MenuController::class, 'show'])->name('menus.show');
  
    // --- Gestion (admin) ---
    Route::middleware(['role:admin'])->group(function () {
        // CRUD
        Route::post  ('/menus',        [MenuController::class, 'store'])->name('menus.store');
        Route::put   ('/menus/{menu}', [MenuController::class, 'update'])->name('menus.update');
        Route::patch ('/menus/{menu}', [MenuController::class, 'update'])->name('menus.patch');
        Route::delete('/menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');
        Route::post('menus/day', [\App\Http\Controllers\MenuController::class, 'storeDailyMenu']);
        // Menus hebdomadaires
        Route::post('/menus/weekly',            [MenuController::class, 'createWeeklyMenu'])->name('menus.weekly.create');
    });

    // Endpoints hebdo accessibles en lecture (authentifié)
    Route::get('/menus/weekly/current', [MenuController::class, 'getCurrentWeekMenu'])->name('menus.weekly.current');
    Route::get('/menus/weekly/by-date', [MenuController::class, 'getWeekMenu'])->name('menus.weekly.byDate');

    // Duplication hebdo (admin)
    Route::middleware(['role:admin'])->post(
        '/menus/weekly/duplicate',
        [MenuController::class, 'duplicateWeeklyMenu']
    )->name('menus.weekly.duplicate');
        // CRUD des activités
        Route::apiResource('activites', ActiviteController::class);
        
        // Actions spéciales sur les activités
        Route::patch('/activites/{activite}/statut', [ActiviteController::class, 'changeStatut']);
        Route::post('/activites/{activite}/duplicate', [ActiviteController::class, 'duplicate']);
        
        // Gestion des inscriptions (Admin peut inscrire/désinscrire n'importe quel enfant)
        Route::post('/activites/{activite}/inscrire', [ActiviteController::class, 'inscrireEnfant']);
        Route::delete('/activites/{activite}/enfants/{enfant}', [ActiviteController::class, 'desinscrireEnfant']);
        
        // Gestion des présences
        Route::post('/activites/{activite}/presences', [ActiviteController::class, 'marquerPresences']);
        // Rôles
        Route::put('/users/{user}/role', [RoleController::class, 'changeRole']);
        Route::get('/users/role/{role}', [RoleController::class, 'getUsersByRole']);
        Route::get('/roles/stats',       [RoleController::class, 'getRoleStats']);

        // Parents
        Route::get(   '/parents',               [ParentController::class, 'index']);
        Route::post(  '/parents',               [ParentController::class, 'store']);
        Route::get(   '/parents/{parent}',      [ParentController::class, 'show']);
        Route::put(   '/parents/{parent}',      [ParentController::class, 'update']);
        Route::delete('/parents/{parent}',      [ParentController::class, 'destroy']);
        Route::patch( '/parents/{parent}/status',[ParentController::class, 'changeStatus']);
        Route::get(   '/parents/stats',         [ParentController::class, 'stats']);

        // Enfants (une seule API d'update via apiResource)
        Route::post('enfants/with-parent', [EnfantController::class, 'storeWithParent'])->name('admin.enfants.store-with-parent');
        Route::apiResource('enfants', EnfantController::class); // PUT|PATCH /api/admin/enfants/{enfant}

        // ─── CLASSES (NEW) ───
        Route::get('/classes/stats', [ClasseController::class, 'stats']); // Doit être avant la route resource
        
        Route::prefix('classes')->group(function () {
            Route::get('/', [ClasseController::class, 'index']); // GET /api/admin/classes - Liste paginée avec filtres
            Route::post('/', [ClasseController::class, 'store']); // POST /api/admin/classes - Créer une classe
            Route::get('/{id}', [ClasseController::class, 'show']); // GET /api/admin/classes/{id} - Détails d'une classe
            Route::put('/{id}', [ClasseController::class, 'update']); // PUT /api/admin/classes/{id} - Modifier une classe
            Route::delete('/{id}', [ClasseController::class, 'destroy']); // DELETE /api/admin/classes/{id} - Supprimer une classe
            
            // CORRECTION: Routes spécialisées AVANT les routes avec paramètres pour éviter les conflits
            Route::get('/list/simple', [ClasseController::class, 'list']); // Liste simple pour sélecteurs
            Route::get('/statistics/all', [ClasseController::class, 'statistics']); // Statistiques complètes
            Route::get('/with/educateurs', [ClasseController::class, 'withEducateurs']); // Classes avec éducateurs
            Route::post('/search', [ClasseController::class, 'search']); // Recherche avancée
            Route::post('/check-nom', [ClasseController::class, 'checkNomDisponibilite']); // Vérification nom
            Route::get('/niveaux/disponibles', [ClasseController::class, 'niveauxDisponibles']); // Niveaux disponibles
            Route::get('/disponibles/affectation', [ClasseController::class, 'disponiblesAffectation']); // Classes disponibles
            Route::get('/export/data', [ClasseController::class, 'export']); // Export des données
            Route::get('/niveau/{niveau}', [ClasseController::class, 'classesByNiveau']); // Classes par niveau
            Route::post('/{id}/duplicate', [ClasseController::class, 'duplicate']); // Duplication
            Route::get('/{id}/rapport', [ClasseController::class, 'rapport']); // Rapport détaillé
            Route::post('/{id}/archiver', [ClasseController::class, 'archiver']); // Archiver une classe
            Route::get('/{id}/can-delete', [ClasseController::class, 'canDelete']); // Vérifier possibilité suppression
        });
        Route::prefix('affectations')->group(function () {
            Route::post('/assign', [ClasseEducateurController::class, 'assignEducateur']);
            Route::delete('/remove', [ClasseEducateurController::class, 'removeEducateur']);
            Route::put('/change', [ClasseEducateurController::class, 'changeEducateur']);
            Route::post('/assign-multiple', [ClasseEducateurController::class, 'assignMultipleEducateurs']);
            Route::get('/resume', [ClasseEducateurController::class, 'getAffectationsResume']);
            Route::get('/classe/{classe}/educateurs-disponibles', [ClasseEducateurController::class, 'getAvailableEducateurs']);
        });
        Route::get('/educateurs/stats', [EducateurController::class, 'stats']); // Doit être avant la route resource
        Route::post('/educateurs/{educateur}/assign-classes', [EducateurController::class, 'assignClasses']);
        Route::prefix('educateurs')->group(function () {
            Route::get('/', [EducateurController::class, 'index']); // Liste des éducateurs
            Route::post('/', [EducateurController::class, 'store']); // Créer un éducateur
            Route::put('/{educateur}', [EducateurController::class, 'update']); // Modifier un éducateur
            Route::delete('/{educateur}', [EducateurController::class, 'destroy']); // Supprimer un éducateur
        });
        // Dashboard (optionnel)
        Route::get('/dashboard', fn () => response()->json(['message' => 'Dashboard Admin']));
    });

    // ─── Éducateurs ───
    Route::prefix('educateur')->middleware('role:educateur')->group(function () {
        Route::get('/dashboard', fn () => response()->json(['message' => 'Dashboard Éducateur']));
        Route::get('/activites', [ActiviteEducateurController::class, 'mesActivites']);
        Route::get('/activites/{activite}', [ActiviteEducateurController::class, 'voirActivite'])
             ->middleware('educateur.activite'); // Middleware custom pour vérifier l'accès
        
        // Planning de l'éducateur
        Route::get('/planning', [ActiviteEducateurController::class, 'planning']);
        
        // Gestion des présences pour une activité
        Route::post('/activites/{activite}/presences', [ActiviteEducateurController::class, 'marquerPresences'])
            ->middleware('educateur.activite');
        
        // Évaluation des enfants
        Route::post('/activites/{activite}/evaluations', [ActiviteEducateurController::class, 'evaluerEnfants'])
            ->middleware('educateur.activite');
        
        // Statistiques éducateur
        Route::get('/activites/stats/mes-stats', [ActiviteEducateurController::class, 'mesStatistiques']);
        // (Existant) Exemple de route liée à une classe
        Route::middleware('educateur.class')->group(function () {
            Route::get('/classes/{classe}/eleves', function ($classe) {
                return response()->json(['message' => "Élèves de la classe {$classe}"]);
            });
        });

        // ─── PRÉSENCE (NEW) ───
        Route::get( '/classes',                         [PresenceController::class, 'getClassesEducateur']);
        Route::get( '/classe/{classeId}/enfants',       [PresenceController::class, 'getEnfantsClasse']);
        Route::post('/presence/marquer',                [PresenceController::class, 'marquerPresenceClasse']);
    });

    // ─── Parents ───
    Route::prefix('parent')->middleware('role:parent')->group(function () {
        Route::get('/dashboard', fn () => response()->json(['message' => 'Dashboard Parent']));
        Route::get('/activites/disponibles', [ActiviteParentController::class, 'activitesDisponibles']);
        
        // Activités de mes enfants
        Route::get('/activites/mes-enfants', [ActiviteParentController::class, 'activitesEnfants']);
        
        // Actions d'inscription/désinscription
        Route::post('/activites/inscrire', [ActiviteParentController::class, 'inscrireEnfant']);
        Route::post('/activites/desinscrire', [ActiviteParentController::class, 'desinscrireEnfant']);
        
        // Consultation des données d'un enfant (protégée par parent.child)
        Route::middleware('parent.child')->group(function () {
            Route::get('/enfants/{enfant}/activites/historique', [ActiviteParentController::class, 'historiqueEnfant']);
            Route::get('/enfants/{enfant}/activites/statistiques', [ActiviteParentController::class, 'statistiquesEnfant']);
            Route::get('/enfants/{enfant}/activites/calendrier', [ActiviteParentController::class, 'calendrierEnfant']);
            Route::get('/activites/{activite}/enfants/{enfant}', [ActiviteParentController::class, 'detailsActiviteEnfant']);
        });
        // Profil
        Route::get('/profile', [ParentController::class, 'profile']);
        Route::put('/profile', [ParentController::class, 'updateProfile']);

        // Notes de l'enfant (protégé par parent.child)
        Route::middleware('parent.child')->group(function () {
            Route::get('/enfants/{enfant}/notes', [ParentController::class, 'getNoteEnfant']);
        });

        // ─── PRÉSENCE (NEW) ───
        Route::get('/enfants',                         [PresenceController::class, 'getEnfantsParent']);
        Route::get('/enfant/{enfantId}/calendrier',    [PresenceController::class, 'getCalendrierEnfant']);
        Route::get('/enfant/{enfantId}/statistiques',  [PresenceController::class, 'getStatistiquesPresence']);
    });

    // ─── Routes partagées (Admin + Parent) — optionnelles ───
    Route::group(['middleware' => 'role:admin,parent'], function () {
        // Exemples éventuels à ajouter ici
    });
    Route::group(['middleware' => 'role:admin,educateur'], function () {
        // Consultation détaillée d'une activité
        Route::get('/activites/{activite}/details', function(Activite $activite) {
            $activite->load(['educateurs', 'enfants' => function($q) {
                $q->withPivot(['statut_participation', 'remarques', 'note_evaluation']);
            }]);
            return response()->json(['success' => true, 'data' => $activite]);
        });
        
        
    });

});