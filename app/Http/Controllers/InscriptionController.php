<?php
// app/Http/Controllers/InscriptionController.php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\InscriptionRequest;
use App\Http\Requests\TraiterInscriptionRequest;
use App\Models\Inscription;
use App\Models\Classe;
use App\Models\Enfant;
use App\Services\InscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class InscriptionController extends Controller
{
    protected $inscriptionService;

    public function __construct(InscriptionService $inscriptionService)
    {
        $this->inscriptionService = $inscriptionService;
    }

    /**
     * Afficher la liste des inscriptions
     */
    public function index(Request $request)
    {
        try {
            $query = Inscription::with(['enfant', 'classe', 'adminTraitant']);
            
            // Filtres
            if ($request->filled('statut')) {
                $query->where('statut', $request->statut);
            }
            
            if ($request->filled('classe_id')) {
                $query->where('classe_id', $request->classe_id);
            }
            
            if ($request->filled('annee_scolaire')) {
                $query->where('annee_scolaire', $request->annee_scolaire);
            }
            
            $inscriptions = $query->orderBy('created_at', 'desc')->paginate(15);
            
            return response()->json([
                'success' => true,
                'data' => $inscriptions
            ]);
        } catch (Exception $e) {
            Log::error('Erreur lors de la récupération des inscriptions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des inscriptions'
            ], 500);
        }
    }

    /**
     * Afficher le formulaire de création d'inscription (pour les vues)
     */
    public function create()
    {
        try {
            $classes = Classe::with(['inscriptionsAcceptees'])
                ->get()
                ->map(function($classe) {
                    return [
                        'id' => $classe->id,
                        'nom' => $classe->nom,
                        'niveau' => $classe->niveau,
                        'capacite_max' => $classe->capacite_max,
                        'places_occupees' => $classe->inscriptionsAcceptees->count(),
                        'places_disponibles' => $classe->capacite_max - $classe->inscriptionsAcceptees->count(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'classes' => $classes
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Erreur lors de la récupération des classes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des classes'
            ], 500);
        }
    }

    /**
     * Enregistrer une nouvelle inscription
     */
    public function store(Request $request)
    {
        try {
            // Validation manuelle pour plus de contrôle
            $validated = $request->validate([
                'enfant_id' => 'required|exists:enfant,id',
                'classe_id' => 'required|exists:classe,id',
                'annee_scolaire' => 'nullable|string|max:9',
                'frais_inscription' => 'nullable|numeric|min:0',
                'frais_mensuel' => 'nullable|numeric|min:0',
                'documents_fournis' => 'nullable|array',
                'remarques' => 'nullable|string|max:1000',
            ]);

            // Vérifier que l'enfant existe
            $enfant = Enfant::findOrFail($validated['enfant_id']);
            
            // Vérifier que la classe existe
            $classe = Classe::findOrFail($validated['classe_id']);

            // Année scolaire par défaut si non fournie
            if (!isset($validated['annee_scolaire'])) {
                $currentYear = now()->year;
                $validated['annee_scolaire'] = $currentYear . '-' . ($currentYear + 1);
            }

            // Vérifier qu'il n'y a pas déjà une inscription active pour cet enfant/classe/année
            $existingInscription = Inscription::where('enfant_id', $validated['enfant_id'])
                ->where('classe_id', $validated['classe_id'])
                ->where('annee_scolaire', $validated['annee_scolaire'])
                ->whereIn('statut', [Inscription::STATUT_EN_ATTENTE, Inscription::STATUT_ACCEPTE, Inscription::STATUT_LISTE_ATTENTE])
                ->first();

            if ($existingInscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet enfant est déjà inscrit pour cette classe et cette année scolaire.'
                ], 422);
            }

            // Utiliser le service pour créer l'inscription
            $inscription = $this->inscriptionService->creerInscription($validated);

            return response()->json([
                'success' => true,
                'message' => 'Inscription créée avec succès.',
                'data' => $inscription
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Erreur lors de la création de l\'inscription: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'inscription: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher une inscription
     */
    public function show(Inscription $inscription)
    {
        try {
            $inscription->load(['enfant', 'classe', 'adminTraitant']);
            
            return response()->json([
                'success' => true,
                'data' => $inscription
            ]);
        } catch (Exception $e) {
            Log::error('Erreur lors de la récupération de l\'inscription: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Inscription non trouvée'
            ], 404);
        }
    }

    /**
     * Traiter une inscription (admin seulement)
     */
    public function traiter(TraiterInscriptionRequest $request, Inscription $inscription)
    {
        try {
            $this->inscriptionService->traiterInscription(
                $inscription,
                $request->action,
                Auth::id(),
                $request->remarques
            );
            
            $message = match($request->action) {
                'accepter' => 'Inscription acceptée avec succès.',
                'refuser' => 'Inscription refusée.',
                'mettre_en_attente' => 'Inscription mise en liste d\'attente.',
            };
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $inscription->fresh(['enfant', 'classe', 'adminTraitant'])
            ]);
                
        } catch (Exception $e) {
            Log::error('Erreur lors du traitement de l\'inscription: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Choisir un candidat depuis la liste d'attente (acceptation directe)
     */
    public function choisirCandidat(Request $request, Classe $classe)
    {
        $request->validate([
            'inscription_id' => 'required|exists:inscriptions,id',
        ]);
        
        try {
            $inscription = $this->inscriptionService->choisirCandidatListeAttente(
                $request->inscription_id, 
                Auth::id()
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Candidat sélectionné et accepté avec succès.',
                'data' => $inscription
            ]);
                
        } catch (Exception $e) {
            Log::error('Erreur lors du choix du candidat: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remettre un candidat en traitement depuis la liste d'attente
     */
    public function remettreEnTraitement(Request $request, Classe $classe)
    {
        $request->validate([
            'inscription_id' => 'required|exists:inscriptions,id',
        ]);
        
        try {
            $inscription = $this->inscriptionService->remettreEnTraitement(
                $request->inscription_id, 
                Auth::id()
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Candidat remis en traitement. Vous pouvez maintenant l\'accepter ou le refuser.',
                'data' => $inscription
            ]);
                
        } catch (Exception $e) {
            Log::error('Erreur lors de la remise en traitement: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activer/Désactiver le mode automatique FIFO pour une classe
     */
    public function toggleModeAutomatique(Request $request, Classe $classe)
    {
        $request->validate([
            'mode_automatique' => 'required|boolean',
        ]);
        
        try {
            // Vous pourriez stocker cette préférence dans une table de paramètres
            // ou dans les métadonnées de la classe
            session(['classe_' . $classe->id . '_mode_auto' => $request->mode_automatique]);
            
            $message = $request->mode_automatique 
                ? 'Mode automatique FIFO activé pour cette classe.'
                : 'Mode de sélection manuelle activé pour cette classe.';
                
            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        } catch (Exception $e) {
            Log::error('Erreur lors du changement de mode: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de mode'
            ], 500);
        }
    }

    /**
     * Libérer une place
     */
    public function liberer(Inscription $inscription)
    {
        try {
            $this->inscriptionService->libererPlace($inscription);
            
            return response()->json([
                'success' => true,
                'message' => 'Place libérée avec succès.'
            ]);
                
        } catch (Exception $e) {
            Log::error('Erreur lors de la libération de place: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher la liste d'attente d'une classe avec options de sélection
     */
    public function listeAttente(Classe $classe, Request $request)
    {
        try {
            $anneeScolaire = $request->get('annee_scolaire', date('Y') . '-' . (date('Y') + 1));
            
            $candidats = $this->inscriptionService->getCandidatsListeAttente($classe->id, $anneeScolaire);
            $statistiques = $this->inscriptionService->getStatistiquesClasse($classe->id, $anneeScolaire);
            
            // Vérifier le mode (automatique ou manuel)
            $modeAutomatique = session('classe_' . $classe->id . '_mode_auto', false);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'classe' => $classe,
                    'candidats' => $candidats,
                    'statistiques' => $statistiques,
                    'annee_scolaire' => $anneeScolaire,
                    'mode_automatique' => $modeAutomatique
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Erreur lors de la récupération de la liste d\'attente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la liste d\'attente'
            ], 500);
        }
    }

    /**
     * Tableau de bord des inscriptions (admin)
     */
    public function dashboard()
    {
        try {
            $anneeCourante = date('Y') . '-' . (date('Y') + 1);
            
            $stats = [
                'en_attente' => Inscription::enAttente()->pourAnnee($anneeCourante)->count(),
                'acceptees' => Inscription::acceptees()->pourAnnee($anneeCourante)->count(),
                'en_liste_attente' => Inscription::enListeAttente()->pourAnnee($anneeCourante)->count(),
                'refusees' => Inscription::refusees()->pourAnnee($anneeCourante)->count(),
            ];
            
            $inscriptionsRecentes = Inscription::with(['enfant', 'classe'])
                                              ->enAttente()
                                              ->orderBy('created_at', 'desc')
                                              ->limit(10)
                                              ->get();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'inscriptions_recentes' => $inscriptionsRecentes
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Erreur lors de la génération du dashboard: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du dashboard'
            ], 500);
        }
    }
}