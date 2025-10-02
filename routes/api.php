<?php

use Illuminate\Support\Facades\Route;

// ───────────── Imports contrôleurs ─────────────
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Parent\ActiviteParticipationController;
use App\Http\Controllers\Parent\ActiviteParentController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\ParentController as AdminParentController;
use App\Http\Controllers\Admin\EnfantController;
use App\Http\Controllers\Admin\ClasseController;
use App\Http\Controllers\Admin\ActiviteController as AdminActiviteController;
use App\Http\Controllers\Admin\EducateurController;
use App\Http\Controllers\Admin\EmploiTemplateController;
use App\Http\Controllers\Admin\EmploiBatchController;
use App\Http\Controllers\Admin\MatiereController;
use App\Http\Controllers\Educateur\GradeController as EduGradeController;
use App\Http\Controllers\Parent\ReportCardController;
use App\Http\Controllers\Educateur\EducateurEmploiController;
use App\Http\Controllers\EmploiQueryController;
use App\Http\Controllers\Chat\ClassChatController;
use App\Http\Controllers\Parent\PresenceController   as ParentPresenceController;
use App\Http\Controllers\Educateur\PresenceController as EducateurPresenceController;
use App\Http\Controllers\PublicInscriptionController;
use App\Http\Controllers\Admin\InscriptionAdminController;
use App\Http\Controllers\PaiementController;
// ──────────────── ROUTES PUBLIQUES ────────────────
Route::get('/test', fn () => response()->json(['message' => 'Hello depuis Laravel']));

// Auth public
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// Exemple public
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

        /* ------------ Menus ------------ */
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

        /* ------------ Activités (admin) ------------ */
        Route::apiResource('activites', AdminActiviteController::class);
        Route::patch ('/activites/{activite}/statut',            [AdminActiviteController::class, 'changeStatut']);
        Route::post  ('/activites/{activite}/duplicate',         [AdminActiviteController::class, 'duplicate']);
        Route::post  ('/activites/{activite}/inscrire',          [AdminActiviteController::class, 'inscrireEnfant']);
        Route::delete('/activites/{activite}/enfants/{enfant}',  [AdminActiviteController::class, 'desinscrireEnfant']);

        /* ------------ Parents (admin) ------------ */
        Route::get   ('/parents',                     [AdminParentController::class, 'index']);
        Route::post  ('/parents',                     [AdminParentController::class, 'store']);
        Route::get   ('/parents/{parent}',            [AdminParentController::class, 'show']);
        Route::put   ('/parents/{parent}',            [AdminParentController::class, 'update']);
        Route::patch ('/parents/{parent}/status',     [AdminParentController::class, 'changeStatus']);
        Route::delete('/parents/{parent}',            [AdminParentController::class, 'destroy']);
        Route::get   ('/parents/stats',               [AdminParentController::class, 'stats']);

        /* ------------ Enfants (admin) ------------ */
        Route::post('enfants/with-parent',            [EnfantController::class, 'storeWithParent'])->name('admin.enfants.store-with-parent');
        Route::apiResource('enfants', EnfantController::class);

        /* ------------ Classes (admin) ------------ */
        Route::get('/classes/stats', [ClasseController::class, 'stats']);
        Route::prefix('classes')->group(function () {
            Route::get   ('/',        [ClasseController::class, 'index']);
            Route::post  ('/',        [ClasseController::class, 'store']);
            Route::get   ('/{id}',    [ClasseController::class, 'show']);
            Route::put   ('/{id}',    [ClasseController::class, 'update']);
            Route::delete('/{id}',    [ClasseController::class, 'destroy']);

            // Spécifiques
            Route::get ('/list/simple',             [ClasseController::class, 'list']);
            Route::get ('/statistics/all',          [ClasseController::class, 'statistics']);
            Route::get ('/with/educateurs',         [ClasseController::class, 'withEducateurs']);
            Route::post('/search',                  [ClasseController::class, 'search']);
            Route::post('/check-nom',               [ClasseController::class, 'checkNomDisponibilite']);
            Route::get ('/niveaux/disponibles',     [ClasseController::class, 'niveauxDisponibles']);
            Route::get ('/disponibles/affectation', [ClasseController::class, 'disponiblesAffectation']);
            Route::get ('/niveau/{niveau}',         [ClasseController::class, 'classesByNiveau']);
            Route::post('/{id}/duplicate',          [ClasseController::class, 'duplicate']);
            Route::get ('/{id}/rapport',            [ClasseController::class, 'rapport']);
            Route::post('/{id}/archiver',           [ClasseController::class, 'archiver']);
            Route::get ('/{id}/can-delete',         [ClasseController::class, 'canDelete']);
        });

        /* ------------ Emplois (admin) ------------ */
        Route::post('/emplois/generate-all', [EmploiBatchController::class, 'generateAll']);
        Route::post('/emploi-templates/generate',                [EmploiTemplateController::class, 'generate']);
        Route::post('/emploi-templates/{tpl}/publish',           [EmploiTemplateController::class, 'publish']);
        Route::patch('/emploi-templates/{tpl}/slots/{slot}',     [EmploiTemplateController::class, 'updateSlot']);
        Route::post('/emploi-templates/{tpl}/slots/{slot}/lock', [EmploiTemplateController::class, 'lock']);

        /* ------------ Uploads ------------ */
        Route::post('matieres/{matiere}/photo', [MatiereController::class, 'uploadPhoto']);

        /* ------------ Educateurs (admin CRUD) ------------ */
        Route::apiResource('educateurs', EducateurController::class);

        /* ------------ Outils par classe (liste d’attente & mode auto) ------------ */

    });

    // ───────────────── Emplois : lecture générique ─────────────────
    Route::get('/emplois/classe/{classe}/template-active', [EmploiTemplateController::class, 'activeForClasse']);
    Route::get('/emplois/classe/{classe}',                 [EmploiQueryController::class, 'byClasse']);
    // (lecture individuelle d’un template; si tu veux le restreindre, déplace-le sous /admin)
    Route::get('/admin/emploi-templates/{tpl}',            [EmploiTemplateController::class, 'show']);

    // ───────────────── Educateurs ─────────────────
    Route::prefix('educateur')->middleware('role:educateur')->group(function () {
        // Emplois pour l’éducateur connecté
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

        // Notes / grades (educateur)
        Route::get ('/grades/roster', [EduGradeController::class, 'roster']);
        Route::post('/grades/bulk',   [EduGradeController::class, 'bulkUpsert']);
    });

    // ───────────────── Parents ─────────────────
    Route::prefix('parent')->middleware('role:parent')->group(function () {
        // Profil & enfants
        Route::get('/profile',  [AdminParentController::class, 'profile']);
        Route::put('/profile',  [AdminParentController::class, 'updateProfile']);

        // Menus côté parent
        Route::get('/menus/weekly/current', [MenuController::class, 'getCurrentWeekMenu']);

        // Enfants & présences
        Route::get('/enfants',                                   [ParentPresenceController::class, 'getEnfantsParent']);
        Route::get('/enfants/{enfantId}/presences',              [ParentPresenceController::class, 'getPresencesEnfant']);
        Route::get('/enfants/{enfantId}/presences/calendrier',   [ParentPresenceController::class, 'getCalendrierEnfant']);

        // Bulletin
        Route::get('/enfants/{id}/report-card', [ReportCardController::class, 'show']);

        // Activités : listing dispo + participation + annulation
        Route::get   ('/activites/disponibles',                        [ActiviteParentController::class, 'activitesDisponibles']);
        Route::post  ('/activites/{activite}/participer',              [ActiviteParticipationController::class, 'participer']);
        Route::delete('/activites/{activite}/participations/{enfant}', [ActiviteParticipationController::class, 'annuler']);
    });

    // ───────────────── Chat (générique) ─────────────────
    Route::get('/chat/rooms',                       [ClassChatController::class,'myRooms']);
    Route::get('/chat/rooms/{room}/participants',   [ClassChatController::class,'participants']);
    Route::get('/chat/rooms/by-classe/{classe}',    [ClassChatController::class,'ensureRoom']); // crée si pas
    Route::get('/chat/rooms/{room}/messages',       [ClassChatController::class,'messages']);
    Route::post('/chat/rooms/{room}/messages',      [ClassChatController::class,'send']);
    Route::post('/chat/rooms/{room}/read',          [ClassChatController::class,'markRead']);

    // Alias historique pour compat avec ton appli Flutter d'avant
    Route::get('/parents/me', [AdminParentController::class, 'profile'])->middleware('role:parent');
Route::post('/inscriptions', [PublicInscriptionController::class, 'store'])
    ->name('public.inscriptions.store');

/**
 * Paiement (token public si tu veux simuler sans être admin)
 * -> facultatif : laisse si tu t’en sers
 */


/**
 * Admin protégées
 */
// --------- PUBLIC ---------
Route::post('/inscriptions', [PublicInscriptionController::class, 'store']);

// Simulation paiement (si tu la gardes)
// routes/api.php
Route::post('/paiements/{paiement}/simulate', [PaiementController::class, 'simulateById'])
    ->name('payments.simulate.id');

// --------- ADMIN PROTÉGÉ ---------
Route::middleware(['auth:sanctum','role:admin'])->prefix('admin')->group(function () {

    Route::get ('/inscriptions',                 [InscriptionAdminController::class, 'index']);
    Route::get ('/inscriptions/{inscription}',   [InscriptionAdminController::class, 'show']);

    // Endpoint principal
    Route::post('/inscriptions/{inscription}/decide', [InscriptionAdminController::class, 'decide']);

    // Alias pratiques (pour Postman, anciennes URLs, etc.)
    Route::post('/inscriptions/{inscription}/accept', [InscriptionAdminController::class, 'accept']);
    Route::post('/inscriptions/{inscription}/wait',   [InscriptionAdminController::class, 'wait']);
    Route::post('/inscriptions/{inscription}/reject', [InscriptionAdminController::class, 'reject']);

    // Affecter/mettre à jour la classe
    Route::post('/inscriptions/{inscription}/assign-class', [InscriptionAdminController::class, 'assignClass']);
});
});
