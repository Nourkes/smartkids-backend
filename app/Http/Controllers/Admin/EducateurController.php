<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEducateurRequest;
use App\Http\Requests\UpdateEducateurRequest;
use App\Http\Requests\UpdateEducateurProfileRequest;
use App\Http\Resources\EducateurResource;
use App\Http\Resources\EducateurCollection;
use App\Models\Educateur;
use App\Services\EducateurService;
use Illuminate\Http\Request;

class EducateurController extends Controller
{
    protected $educateurService;

    public function __construct(EducateurService $educateurService)
    {
        $this->educateurService = $educateurService;
    }

    /**
     * Liste des éducateurs (Admin uniquement)
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');
        
        $educateurs = $this->educateurService->getAllEducateurs($perPage, $search);
        
        return new EducateurCollection($educateurs);
    }

    /**
     * Créer un nouvel éducateur (Admin uniquement)
     */
    public function store(StoreEducateurRequest $request)
    {
        $educateur = $this->educateurService->createEducateur($request->validated());
        return new EducateurResource($educateur);
    }


    /**
     * Afficher un éducateur spécifique
     */
    public function show($id, EducateurService $service)
    {
        $educateur = $service->getEducateurById($id); // <- ta méthode
        // renvoyer sous la clé "data" comme le reste de l’API
        return response()->json(['data' => $educateur], 200);
    }

    /**
     * Mettre à jour un éducateur (Admin uniquement)
     */
    public function update(UpdateEducateurRequest $request, Educateur $educateur)
    {
        $educateur = $this->educateurService->updateEducateur($educateur, $request->validated());
        
        return new EducateurResource($educateur);
    }

    /**
     * Mettre à jour le profil de l'éducateur (Éducateur lui-même)
     */
    public function updateProfile(UpdateEducateurProfileRequest $request, Educateur $educateur)
    {
        $educateur = $this->educateurService->updateEducateurProfile($educateur, $request->validated());
        
        return new EducateurResource($educateur);
    }

    /**
     * Supprimer un éducateur (Admin uniquement)
     */
    public function destroy(Educateur $educateur)
    {
        $this->educateurService->deleteEducateur($educateur);
        
        return response()->json(['message' => 'Éducateur supprimé avec succès'], 200);
    }

    /**
     * Obtenir le profil de l'éducateur connecté
     */
    public function profile()
    {
        $user = auth()->user();
        
        if (!$user->isEducateur()) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }
        
        return new EducateurResource($user->educateur->load(['user', 'classes', 'activites']));
    }
}