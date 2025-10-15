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

    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $limit = (int) $request->query('limit', 25);
            $search = trim($request->query('search', ''), '"\'');
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');

            // 🔹 Consulta base: movimentos da empresa do usuário
            $query = \App\Models\InventoryMovementsModel::with([
                'product' => function ($q) {
                    $q->withTrashed();
                },
                'warehouse' => function ($q) {
                    $q->withTrashed();
                }
            ])->where('company_id', $user->company_id);

            // 🔹 Filtro de busca (por tipo, produto ou armazém)
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('movement_type', 'LIKE', "%{$search}%")
                        ->orWhereHas('product', function ($p) use ($search) {
                            $p->where('name', 'LIKE', "%{$search}%");
                        })
                        ->orWhereHas('warehouse', function ($w) use ($search) {
                            $w->where('name', 'LIKE', "%{$search}%");
                        });
                });
            }

            // 🔹 Filtro por data de início
            if (!empty($startDate)) {
                $query->whereDate('created_at', '>=', $startDate);
            }

            // 🔹 Filtro por data final
            if (!empty($endDate)) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            // 🔹 Ordenação (mais recentes primeiro)
            $query->orderBy('created_at', 'desc');

            // 🔹 Paginação automática do Laravel
            $movements = $query->paginate($limit);

            // 🔹 Retorno padronizado
            return response()->json([
                'success' => true,
                'data' => $movements->items(),
                'pagination' => [
                    'page' => $movements->currentPage(),
                    'limit' => $movements->perPage(),
                    'page_count' => $movements->lastPage(),
                    'total_count' => $movements->total(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error while listing inventory movements.',
                'error' => $e->getMessage(),
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
