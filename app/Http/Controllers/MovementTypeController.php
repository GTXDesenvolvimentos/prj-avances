<?php

namespace App\Http\Controllers;

use App\Models\MovementTypeModel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MovementTypeController extends Controller
{
    /**
     * List all movement types (index)
     */
    public function index()
    {

        try {

            // Obtém o ID da empresa do usuário logado
            $companyId = auth()->user()->company_id;

            // Busca apenas os MovementTypes pertencentes à empresa do usuário
            $movementTypes = MovementTypeModel::with(relations: 'company')
                ->where('company_id', $companyId)
                ->get();


            return response()->json([
                'success' => true,
                'data' => $movementTypes
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error while listing movement types.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new movement type (store)
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $request->merge(['company_id' => $user->company_id]);
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $movementType = MovementTypeModel::create($request->all());
            return response()->json([
                'success' => true,
                'message' => 'Movement type successfully created!',
                'data' => $movementType
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error while creating movement type.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show a specific movement type (show)
     */
    public function show($id)
    {
        try {
            $movementType = MovementTypeModel::with('company')->findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $movementType
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Movement type not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error while fetching movement type.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a movement type (update)
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        $movement = MovementTypeModel::where('company_id', $user->company_id)
            ->find($id);

        if (!$movement) {
            return response()->json([
                'success' => false,
                'message' => 'Tipo de movimento não encontrado.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:255',
            'type' => 'sometimes|required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $movement->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Tipo de movimento atualizado com sucesso.',
                'data' => $movement
            ]);

        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar tipo de movimento.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Soft delete a movement type (destroy)
     */
    public function destroy($id)
    {
        $user = auth()->user(); // Usuário autenticado

        try {
            $movementType = MovementTypeModel::findOrFail($id);

            // Verifica se o movimento pertence à mesma empresa do usuário
            if ($movementType->company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to delete this type of movement.'
                ], 403);
            }

            $movementType->delete();

            return response()->json([
                'success' => true,
                'message' => 'Movement type successfully deleted!'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Movement type not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error while deleting movement type.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
