<?php

use Illuminate\Support\Facades\Route;

// ───────────── Imports ─────────────
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RoleController;

use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\ParentController as AdminParentController;
use App\Http\Controllers\Admin\EnfantController;
use App\Http\Controllers\Admin\ClasseController;
use App\Http\Controllers\Admin\ActiviteController as AdminActiviteController;
use App\Http\Controllers\Admin\EducateurController;
use App\Http\Controllers\Admin\EmploiTemplateController;
use App\Http\Controllers\Admin\EmploiBatchController;
use App\Http\Controllers\Admin\MatiereController;

use App\Http\Controllers\Educateur\EducateurEmploiController;
use App\Http\Controllers\EmploiQueryController;

// Présences (⚠️ alias pour lever la collision de nom)
use App\Http\Controllers\Parent\PresenceController   as ParentPresenceController;
use App\Http\Controllers\Educateur\PresenceController as EducateurPresenceController;

// (optionnels)
use App\Http\Controllers\Educateur\ActiviteController as ActiviteEducateurController;
use App\Http\Controllers\Parent\ActiviteController    as ActiviteParentController;

// ──────────────── ROUTES PUBLIQUES ────────────────
Route::get('/test', fn () => response()->json(['message' => 'Hello depuis Laravel']));

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// (exemple public)
Route::get('/activites/types', [AdminActiviteController::class, 'typesPublic']);

// ──────────────── ROUTES PROTÉGÉES ────────────────
Route::middleware('auth:sanctum')->group(function () {

    // ─── Session / profil générique ───
    Route::prefix('auth')->group(function () {
        Route::post('/logout',        [AuthController::class, 'logout']);
        Route::post('/logout-all',    [AuthController::class, 'logoutAll']);
        Route::get ('/me',            [AuthController::class, 'me']);
        Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
    });

    // ───────────────── Admin ─────────────────
    Route::prefix('admin')->middleware('role:admin')->group(function () {

        // Menus
        Route::get   ('/menus',                [MenuController::class, 'index'])->name('menus.index');
        Route::get   ('/menus/{menu}',         [MenuController::class, 'show'])->name('menus.show');
        Route::post  ('/menus',                [MenuController::class, 'store'])->name('menus.store');
        Route::put   ('/menus/{menu}',         [MenuController::class, 'update'])->name('menus.update');
        Route::patch ('/menus/{menu}',         [MenuController::class, 'update'])->name('menus.patch');
        Route::delete('/menus/{menu}',         [MenuController::class, 'destroy'])->name('menus.destroy');
        Route::post  ('/menus/day',            [MenuController::class, 'storeDailyMenu'])->name('menus.day.store');
        Route::post  ('/menus/weekly',         [MenuController::class, 'createWeeklyMenu'])->name('menus.weekly.create');
        Route::get   ('/menus/weekly/current', [MenuController::class, 'getCurrentWeekMenu'])->name('menus.weekly.current');
        Route::get   ('/menus/weekly/by-date', [MenuController::class, 'getWeekMenu'])->name('menus.weekly.byDate');
        Route::post  ('/menus/weekly/duplicate',[MenuController::class, 'duplicateWeeklyMenu'])->name('menus.weekly.duplicate');

        // Activités (admin)
        Route::apiResource('activites', AdminActiviteController::class);
        Route::patch ('/activites/{activite}/statut',     [AdminActiviteController::class, 'changeStatut']);
        Route::post  ('/activites/{activite}/duplicate',  [AdminActiviteController::class, 'duplicate']);
        Route::post  ('/activites/{activite}/inscrire',   [AdminActiviteController::class, 'inscrireEnfant']);
        Route::delete('/activites/{activite}/enfants/{enfant}', [AdminActiviteController::class, 'desinscrireEnfant']);

        // Parents CRUD (admin)
        Route::get   ('/parents',          [AdminParentController::class, 'index']);
        Route::post  ('/parents',          [AdminParentController::class, 'store']);
        Route::get   ('/parents/{parent}', [AdminParentController::class, 'show']);
        Route::put   ('/parents/{parent}', [AdminParentController::class, 'update']);
        Route::patch ('/parents/{parent}/status', [AdminParentController::class, 'changeStatus']);
        Route::delete('/parents/{parent}', [AdminParentController::class, 'destroy']);
        Route::get   ('/parents/stats',    [AdminParentController::class, 'stats']);

        // Enfants CRUD
        Route::post('enfants/with-parent', [EnfantController::class, 'storeWithParent'])->name('admin.enfants.store-with-parent');
        Route::apiResource('enfants', EnfantController::class);

        // Classes
        Route::get('/classes/stats', [ClasseController::class, 'stats']);
        Route::prefix('classes')->group(function () {
            Route::get   ('/',        [ClasseController::class, 'index']);
            Route::post  ('/',        [ClasseController::class, 'store']);
            Route::get   ('/{id}',    [ClasseController::class, 'show']);
            Route::put   ('/{id}',    [ClasseController::class, 'update']);
            Route::delete('/{id}',    [ClasseController::class, 'destroy']);

            // Spécifiques
            Route::get ('/list/simple',            [ClasseController::class, 'list']);
            Route::get ('/statistics/all',         [ClasseController::class, 'statistics']);
            Route::get ('/with/educateurs',        [ClasseController::class, 'withEducateurs']);
            Route::post('/search',                 [ClasseController::class, 'search']);
            Route::post('/check-nom',              [ClasseController::class, 'checkNomDisponibilite']);
            Route::get ('/niveaux/disponibles',    [ClasseController::class, 'niveauxDisponibles']);
            Route::get ('/disponibles/affectation',[ClasseController::class, 'disponiblesAffectation']);
            Route::get ('/niveau/{niveau}',        [ClasseController::class, 'classesByNiveau']);
            Route::post('/{id}/duplicate',         [ClasseController::class, 'duplicate']);
            Route::get ('/{id}/rapport',           [ClasseController::class, 'rapport']);
            Route::post('/{id}/archiver',          [ClasseController::class, 'archiver']);
            Route::get ('/{id}/can-delete',        [ClasseController::class, 'canDelete']);
        });

        // Emplois (batch + templates)
        Route::post('/emplois/generate-all', [EmploiBatchController::class, 'generateAll']);
        Route::post('/emploi-templates/generate',                [EmploiTemplateController::class, 'generate']);
        Route::post('/emploi-templates/{tpl}/publish',           [EmploiTemplateController::class, 'publish']);
        Route::patch('/emploi-templates/{tpl}/slots/{slot}',     [EmploiTemplateController::class, 'updateSlot']);
        Route::post('/emploi-templates/{tpl}/slots/{slot}/lock', [EmploiTemplateController::class, 'lock']);

        // Uploads
        Route::post('matieres/{matiere}/photo', [MatiereController::class, 'uploadPhoto']);
    });

    // Emplois (lecture “générique”)
    Route::get('/emplois/classe/{classe}/template-active', [EmploiTemplateController::class, 'activeForClasse']);
    Route::get('/emplois/classe/{classe}',                 [EmploiQueryController::class, 'byClasse']);
    Route::get('/admin/emploi-templates/{tpl}',            [EmploiTemplateController::class, 'show']); // lecture individuelle

    // ───────────────── Éducateurs ─────────────────
    Route::prefix('educateur')->middleware('role:educateur')->group(function () {
        // Emplois “self”
        Route::get('/emploi/jour',     [EducateurEmploiController::class, 'day']);     // ?date=YYYY-MM-DD
        Route::get('/emplois/annee',   [EducateurEmploiController::class, 'yearSelf']);
        Route::get('/emplois/semaine', [EducateurEmploiController::class, 'weekSelf']);

        // Présences (educateur)
        Route::get ('/classes',                                [EducateurPresenceController::class, 'getClassesEducateur']);
        Route::get ('/classes/{classeId}/presences',           [EducateurPresenceController::class, 'getEnfantsClasse']);
        Route::post('/classes/{classeId}/presences',           [EducateurPresenceController::class, 'marquerPresences']);
        Route::get ('/classes/{classeId}/presences/historique',[EducateurPresenceController::class, 'getHistoriqueClasse']);
        Route::put ('/presences/{presenceId}',                 [EducateurPresenceController::class, 'updatePresence']);
        Route::get ('/presences/statistiques',                 [EducateurPresenceController::class, 'getStatistiquesEducateur']);
    });

    // ───────────────── Parents ─────────────────
    Route::prefix('parent')->middleware('role:parent')->group(function () {
        // Profil & enfants
        Route::get('/profile',  [AdminParentController::class, 'profile']);
        Route::put('/profile',  [AdminParentController::class, 'updateProfile']);

        Route::get('/enfants',  [ParentPresenceController::class, 'getEnfantsParent']); // <= EXISTE dans ton contrôleur

        // Présences (chemins alignés sur ton contrôleur)
        Route::get('/enfants/{enfantId}/presences',                 [ParentPresenceController::class, 'getPresencesEnfant']);
        Route::get('/enfants/{enfantId}/presences/calendrier',      [ParentPresenceController::class, 'getCalendrierEnfant']);

        // (Si tu ajoutes un jour un contrôleur d’activités parent)
        // Route::get('/activites/disponibles', [ActiviteParentController::class, 'activitesDisponibles']);
    });

    // Alias historique pour compat avec ton appli Flutter d'avant
    Route::get('/parents/me', [AdminParentController::class, 'profile'])->middleware('role:parent');
});
