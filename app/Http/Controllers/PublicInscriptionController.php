<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInscriptionRequest;
use App\Http\Resources\InscriptionResource;
use App\Services\InscriptionFlowService;
use Illuminate\Http\JsonResponse;

class PublicInscriptionController extends Controller
{
    public function __construct(private InscriptionFlowService $flow) {}

    public function store(StoreInscriptionRequest $request): JsonResponse
    {
        $i = $this->flow->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Demande enregistrée. Vous serez contacté par email.',
            'data'    => new InscriptionResource($i),
        ], 201);
    }
}
