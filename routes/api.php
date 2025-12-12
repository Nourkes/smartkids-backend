<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Carbon\Carbon;

/* ───────────── Imports contrôleurs ───────────── */
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\PublicPaymentController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\ParentController as AdminParentController;
use App\Http\Controllers\Admin\EnfantController;
use App\Http\Controllers\Admin\ClasseController;
use App\Http\Controllers\Admin\ActiviteController as AdminActiviteController;
use App\Http\Controllers\Admin\EducateurController;
use App\Http\Controllers\Admin\EmploiTemplateController;
use App\Http\Controllers\Admin\EmploiBatchController;
use App\Http\Controllers\Admin\MatiereController;
use App\Http\Controllers\Admin\InscriptionAdminController;
use App\Http\Controllers\Parent\ActivitePaymentController;
use App\Http\Controllers\Educateur\GradeController as EduGradeController;
use App\Http\Controllers\Educateur\EducateurEmploiController;
use App\Http\Controllers\Educateur\PresenceController as EducateurPresenceController;

use App\Http\Controllers\Parent\PresenceController as ParentPresenceController;
use App\Http\Controllers\Parent\ReportCardController;
use App\Http\Controllers\Parent\ActiviteParticipationController;
use App\Http\Controllers\Parent\ActiviteParentController;

use App\Http\Controllers\EmploiQueryController;
use App\Http\Controllers\Chat\ClassChatController;

use App\Http\Controllers\PublicInscriptionController;
use App\Http\Controllers\PaiementController;

use App\Http\Controllers\Statistics\GlobalStatisticsController;
use App\Http\Controllers\Statistics\PaymentsStatisticsController;
use App\Http\Controllers\PaymentQuotesController;
use App\Http\Controllers\Parent\EnrollmentStatusController;
/* ───────────── Services (pour closures quote/create) ───────────── */
use App\Services\PaymentService;
use App\Models\Inscription;
use App\Models\Paiement;
/* ──────────────── ROUTES PUBLIQUES ──────────────── */
Route::get('/test', fn() => response()->json(['message' => 'Hello depuis Laravel']));

/* Auth public */
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});
Route::prefix('public/payments')->group(function () {
    Route::get('/{token}/quote', [PublicPaymentController::class, 'quote']);
    Route::post('/{token}/confirm', [PublicPaymentController::class, 'confirm'])
        ->middleware(['throttle:10,1']); // anti-bruteforce basique

});
/* Endpoints publics divers */
Route::get('/activites/types', [AdminActiviteController::class, 'typesPublic']);

/* Fichiers de chat publics (si désiré) */
Route::get('/chat/file/{path}', [ClassChatController::class, 'file'])
    ->where('path', '.*');

/* Inscription publique (FORM) */
Route::post('/inscriptions', [PublicInscriptionController::class, 'store'])
    ->name('public.inscriptions.store');

/* Simulation paiement (garde public si tu veux tester sans auth, sinon protège-le) */
Route::post('/paiements/{paiement}/simulate', [PaiementController::class, 'simulateById'])
    ->name('payments.simulate.id');

/* ──────────────── ROUTES PROTÉGÉES ──────────────── */
Route::middleware('auth:sanctum')->group(function () {
    /* ──────────────── Chat ──────────────── */
    Route::prefix('chat')->group(function () {

        // 👉 Liste des salons où l'utilisateur est membre
        //    → correspond à GET /api/chat/rooms
        Route::get('/rooms', [ClassChatController::class, 'myRooms']);

        // Créer / récupérer le salon associé à une classe
        //    → /api/chat/classes/{classe}/room
        Route::post('/classes/{classe}/room', [ClassChatController::class, 'ensureRoom']);

        // Messages d’un salon
        Route::get('/rooms/{room}/messages', [ClassChatController::class, 'messages']);
        Route::post('/rooms/{room}/messages', [ClassChatController::class, 'send']);

        // Participants
        Route::get('/rooms/{room}/participants', [ClassChatController::class, 'participants']);

        // Marquer comme lu
        Route::post('/rooms/{room}/read', [ClassChatController::class, 'markRead']);
    });
    /* ─── Session / profil générique ─── */
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh-token', [AuthController::class, 'refreshToken']);

        Route::post('/first-login/send-code', [AuthController::class, 'sendFirstLoginCode']);
        Route::post('/first-login/verify', [AuthController::class, 'verifyFirstLoginCode']);
        Route::post('/first-login/reset-password', [AuthController::class, 'resetFirstLoginPassword']);
    });

    /* ───────────────── Admin ───────────────── */
    Route::prefix('admin')->middleware('role:admin')->group(function () {

        /* Menus */
        Route::get('/menus', [MenuController::class, 'index'])->name('menus.index');
        Route::get('/menus/{menu}', [MenuController::class, 'show'])->name('menus.show');
        Route::post('/menus', [MenuController::class, 'store'])->name('menus.store');
        Route::put('/menus/{menu}', [MenuController::class, 'update'])->name('menus.update');
        Route::patch('/menus/{menu}', [MenuController::class, 'update'])->name('menus.patch');
        Route::delete('/menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');
        Route::post('/menus/day', [MenuController::class, 'storeDailyMenu'])->name('menus.day.store');
        Route::post('/menus/weekly', [MenuController::class, 'createWeeklyMenu'])->name('menus.weekly.create');
        Route::get('/menus/weekly/current', [MenuController::class, 'getCurrentWeekMenu'])->name('menus.weekly.current');
        Route::get('/menus/weekly/by-date', [MenuController::class, 'getWeekMenu'])->name('menus.weekly.byDate');
        Route::post('/menus/weekly/duplicate', [MenuController::class, 'duplicateWeeklyMenu'])->name('menus.weekly.duplicate');

        /* Activités (admin) */
        Route::apiResource('activites', AdminActiviteController::class);
        Route::patch('/activites/{activite}/statut', [AdminActiviteController::class, 'changeStatut']);
        Route::post('/activites/{activite}/duplicate', [AdminActiviteController::class, 'duplicate']);
        Route::post('/activites/{activite}/inscrire', [AdminActiviteController::class, 'inscrireEnfant']);
        Route::delete('/activites/{activite}/enfants/{enfant}', [AdminActiviteController::class, 'desinscrireEnfant']);

        /* Parents (admin) */
        Route::get('/parents', [AdminParentController::class, 'index']);
        Route::post('/parents', [AdminParentController::class, 'store']);
        Route::get('/parents/{parent}', [AdminParentController::class, 'show']);
        Route::put('/parents/{parent}', [AdminParentController::class, 'update']);
        Route::patch('/parents/{parent}/status', [AdminParentController::class, 'changeStatus']);
        Route::delete('/parents/{parent}', [AdminParentController::class, 'destroy']);
        Route::get('/parents/stats', [AdminParentController::class, 'stats']);

        /* Enfants (admin) */
        Route::post('enfants/with-parent', [EnfantController::class, 'storeWithParent'])->name('admin.enfants.store-with-parent');
        Route::apiResource('enfants', EnfantController::class);

        /* Classes (admin) */
        Route::get('/classes/stats', [ClasseController::class, 'stats']);
        Route::prefix('classes')->group(function () {
            Route::get('/', [ClasseController::class, 'index']);
            Route::post('/', [ClasseController::class, 'store']);
            Route::get('/{id}', [ClasseController::class, 'show']);
            Route::put('/{id}', [ClasseController::class, 'update']);
            Route::delete('/{id}', [ClasseController::class, 'destroy']);

            Route::get('/list/simple', [ClasseController::class, 'list']);
            Route::get('/statistics/all', [ClasseController::class, 'statistics']);
            Route::get('/with/educateurs', [ClasseController::class, 'withEducateurs']);
            Route::post('/search', [ClasseController::class, 'search']);
            Route::post('/check-nom', [ClasseController::class, 'checkNomDisponibilite']);
            Route::get('/niveaux/disponibles', [ClasseController::class, 'niveauxDisponibles']);
            Route::get('/disponibles/affectation', [ClasseController::class, 'disponiblesAffectation']);
            Route::get('/niveau/{niveau}', [ClasseController::class, 'classesByNiveau']);
            Route::post('/{id}/duplicate', [ClasseController::class, 'duplicate']);
            Route::get('/{id}/rapport', [ClasseController::class, 'rapport']);
            Route::post('/{id}/archiver', [ClasseController::class, 'archiver']);
            Route::get('/{id}/can-delete', [ClasseController::class, 'canDelete']);
        });

        /* Emplois (admin) */
        Route::post('/emplois/generate-all', [EmploiBatchController::class, 'generateAll']);
        Route::post('/emploi-templates/generate', [EmploiTemplateController::class, 'generate']);
        Route::post('/emploi-templates/{tpl}/publish', [EmploiTemplateController::class, 'publish']);
        Route::patch('/emploi-templates/{tpl}/slots/{slot}', [EmploiTemplateController::class, 'updateSlot']);
        Route::post('/emploi-templates/{tpl}/slots/{slot}/lock', [EmploiTemplateController::class, 'lock']);

        /* Uploads */
        Route::post('matieres/{matiere}/photo', [MatiereController::class, 'uploadPhoto']);

        /* Educateurs (admin CRUD) */
        Route::apiResource('educateurs', EducateurController::class);

        /* Inscriptions (admin) */
        Route::get('/inscriptions', [InscriptionAdminController::class, 'index']);
        Route::get('/inscriptions/{inscription}', [InscriptionAdminController::class, 'show']);
        Route::post('/inscriptions/{inscription}/decide', [InscriptionAdminController::class, 'decide']);
        Route::post('/inscriptions/{inscription}/accept', [InscriptionAdminController::class, 'accept']);
        Route::post('/inscriptions/{inscription}/wait', [InscriptionAdminController::class, 'wait']);
        Route::post('/inscriptions/{inscription}/reject', [InscriptionAdminController::class, 'reject']);
        Route::post('/inscriptions/{inscription}/assign-class', [InscriptionAdminController::class, 'assignClass']);

        /* Housekeeping paiements (admin) */
        Route::post('/payments/expire-overdue', function () {
            $n = app(\App\Services\PaymentHousekeepingService::class)->massExpireOverdue();
            return response()->json(['expired_and_cleaned' => $n]);
        });
    });

    /* ───────────────── Emplois : lecture générique ───────────────── */
    Route::get('/emplois/classe/{classe}/template-active', [EmploiTemplateController::class, 'activeForClasse']);
    Route::get('/emplois/classe/{classe}', [EmploiQueryController::class, 'byClasse']);
    Route::get('/admin/emploi-templates/{tpl}', [EmploiTemplateController::class, 'show']); // lecture seule

    /* ───────────────── Educateurs (role) ───────────────── */
    Route::prefix('educateur')->middleware('role:educateur')->group(function () {
        // Emplois
        Route::get('/emploi/jour', [EducateurEmploiController::class, 'day']);     // ?date=YYYY-MM-DD
        Route::get('/emplois/annee', [EducateurEmploiController::class, 'yearSelf']);
        Route::get('/emplois/semaine', [EducateurEmploiController::class, 'weekSelf']);

        // Présences
        Route::get('/classes', [EducateurPresenceController::class, 'getClassesEducateur']);
        Route::get('/classes/{classeId}/presences', [EducateurPresenceController::class, 'getEnfantsClasse']);
        Route::post('/classes/{classeId}/presences', [EducateurPresenceController::class, 'marquerPresences']);
        Route::get('/classes/{classeId}/presences/historique', [EducateurPresenceController::class, 'getHistoriqueClasse']);
        Route::put('/presences/{presenceId}', [EducateurPresenceController::class, 'updatePresence']);
        Route::get('/presences/statistiques', [EducateurPresenceController::class, 'getStatistiquesEducateur']);

        // Notes
        Route::get('/grades/roster', [EduGradeController::class, 'roster']);
        Route::post('/grades/bulk', [EduGradeController::class, 'bulkUpsert']);
    });

    /* ───────────────── Parents (role) ───────────────── */

    // Endpoint de vérification du statut d'inscription (AVANT le middleware active.enrollment)
    Route::get('/parent/enrollment-status', [EnrollmentStatusController::class, 'checkStatus'])
        ->middleware('role:parent');

    // Routes parent protégées par le middleware active.enrollment
    Route::prefix('parent')->middleware(['role:parent', 'active.enrollment'])->group(function () {
        // Profil
        Route::get('/profile', [AdminParentController::class, 'profile']);
        Route::put('/profile', [AdminParentController::class, 'updateProfile']);

        // Menus
        Route::get('/menus/weekly/current', [MenuController::class, 'getCurrentWeekMenu']);

        // Enfants & présences
        Route::get('/enfants', [ParentPresenceController::class, 'getEnfantsParent']);
        Route::get('/enfants/{enfantId}/presences', [ParentPresenceController::class, 'getPresencesEnfant']);
        Route::get('/enfants/{enfantId}/presences/calendrier', [ParentPresenceController::class, 'getCalendrierEnfant']);

        // Bulletin
        Route::get('/enfants/{id}/report-card', [ReportCardController::class, 'show']);

        // Activités côté parent
        Route::get('/activites/disponibles', [ActiviteParentController::class, 'activitesDisponibles']);
        Route::post('/activites/{activite}/participer', [ActiviteParticipationController::class, 'participer']);
        Route::delete('/activites/{activite}/participations/{enfant}', [ActiviteParticipationController::class, 'annuler']);
        Route::get('/activites/{activite}/payment/quote', [ActivitePaymentController::class, 'quote']);
        Route::post('/activites/{activite}/payment/confirm', [ActivitePaymentController::class, 'confirm']);


    });


    /* ───────────────── Paiements : devis & création (parents + admin) ─────────────────
       Spatie/Permission supporte "role:admin|parent" */
    // routes/api.php


    // … (tes autres use et groupes restent identiques)

    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
        // Paiement scolarité — devis + création

        Route::get('/inscriptions/{inscription}/monthly/quote', [PaymentQuotesController::class, 'monthlyQuote']);
        Route::post('/inscriptions/{inscription}/monthly/create', [PaymentQuotesController::class, 'monthlyCreate']);
    });





    // Simulation d’un paiement (protège si tu veux)
    Route::post('/paiements/{paiement}/simulate', [PaiementController::class, 'simulateById'])
        ->middleware('auth:sanctum')   // ← enlève si tu veux le laisser public pour tests
        ->name('payments.simulate.id');

    /* ───────────────── Statistiques (protégées) ───────────────── */
    Route::get('/statistics/global/basic', [GlobalStatisticsController::class, 'basic'])
        ->middleware('role:admin');
    Route::get('/statistics/payments/revenue-series', [PaymentsStatisticsController::class, 'revenueSeries'])
        ->middleware('role:admin');
    Route::get('/statistics/payments/financial-overview', [PaymentsStatisticsController::class, 'financialOverview'])
        ->middleware('role:admin');

});
