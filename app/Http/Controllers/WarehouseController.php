<?php

namespace App\Http\Controllers;
use App\Models\WarehouseModel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WarehouseController extends Controller
{
    /**
     * List all warehouses (index)
     */
    public function index()
    {
        try {
            // Obtém o ID da empresa do usuário logado
            $companyId = auth()->user()->company_id;

            // Busca apenas os warehouses da empresa do usuário
            $warehouses = WarehouseModel::with('company')
                ->where('company_id', $companyId)
                ->orderBy('created_at', 'desc') // 🔹 Ordena do mais recente para o mais antigo
                ->get();

            return response()->json([
                'success' => true,
                'data' => $warehouses
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error while listing warehouses.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new warehouse (store)
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $request->merge(['company_id' => $user->company_id]);

        $validator = Validator::make($request->all(), [
            'address_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'note' => 'nullable|string',
            'company_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $warehouse = WarehouseModel::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Warehouse successfully created!',
                'data' => $warehouse
            ], 201);

        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database error while creating warehouse.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show a specific warehouse
     */
    public function show($id)
    {
        try {
            $warehouse = WarehouseModel::find($id);

            if (!$warehouse) {
                return response()->json([
                    'success' => false,
                    'message' => 'Warehouse not found.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $warehouse
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error while retrieving warehouse.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing warehouse
     */
    public function update(Request $request, $id)
    {
        try {
            $warehouse = WarehouseModel::find($id);

            if (!$warehouse) {
                return response()->json([
                    'success' => false,
                    'message' => 'Warehouse not found.'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'address_id' => 'sometimes|required|integer',
                'name' => 'sometimes|required|string|max:255',
                'note' => 'nullable|string',
                'status' => 'sometimes|required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $warehouse->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Warehouse successfully updated!',
                'data' => $warehouse
            ], 200);

        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database error while updating warehouse.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Soft delete a warehouse
     */
    public function destroy($id)
    {
        try {
            $warehouse = WarehouseModel::find($id);

            if (!$warehouse) {
                return response()->json([
                    'success' => false,
                    'message' => 'Warehouse not found.'
                ], 404);
            }

            $warehouse->delete();

            return response()->json([
                'success' => true,
                'message' => 'Warehouse successfully deleted.'
            ], 200);

        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error while deleting warehouse.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
