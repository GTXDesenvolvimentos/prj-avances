<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovementsModel;
use App\Models\MovementTypeModel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class InventoryMovementsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $companyId = $user->company_id;

            // 🔹 Parâmetros de paginação e busca
            $limit = (int) $request->query('limit', 25);
            $search = trim($request->query('search', ''), '"\'');
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');

            // 🔹 Consulta base: movimentos da empresa do usuário
            $query = InventoryMovementsModel::with([
                'product' => function ($q) {
                    $q->withTrashed();
                },
                'warehouse' => function ($q) {
                    $q->withTrashed();
                },
                'moviment_type' => function ($q) {
                    $q->withTrashed();
                }
            ])->where('company_id', $companyId);

            // 🔹 Filtro de busca (por tipo, produto ou armazém)
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('movement_type', 'LIKE', "%{$search}%")
                        ->orWhereHas('product', function ($p) use ($search) {
                            $p->where('name', 'LIKE', "%{$search}%");
                        })
                        ->orWhereHas('warehouse', function ($w) use ($search) {
                            $w->where('name', 'LIKE', "%{$search}%");
                        })
                        ->orWhere('notes', 'LIKE', "%{$search}%");
                });
            }

            // 🔹 Filtro por data inicial
            if (!empty($startDate)) {
                $query->whereDate('created_at', '>=', $startDate);
            }

            // 🔹 Filtro por data final
            if (!empty($endDate)) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            // 🔹 Ordenação (mais recentes primeiro)
            $query->orderBy('created_at', 'desc');

            // 🔹 Paginação
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
                'message' => 'Inventory movements were successfully retrieved.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving inventory movements.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }




    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */


    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            DB::beginTransaction();

            // Validação dos dados
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|integer|exists:products,id',
                'warehouse_id' => 'required|integer|exists:warehouses,id',
                'movement_type' => 'required|integer|exists:movement_type,id',
                'rental_rental_id' => 'nullable|integer|exists:rentals,id',
                'sale_sale_id' => 'nullable|integer|exists:sales,id',
                'quantity_movement' => 'required|numeric|min:0.01',
                'notes' => 'nullable|string|max:500',
                'company_id' => 'required|integer|exists:companies,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();

            // 2️⃣ Buscar o tipo de movimento no banco
            $movementType = MovementTypeModel::find($validated['movement_type']);
            $lastMovement = InventoryMovementsModel::where('product_id', $validated['product_id'])
                ->where('warehouse_id', $validated['warehouse_id'])
                ->orderBy('id', 'desc')
                ->first();

            if (!$movementType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Movement type not found.',
                ], 404);
            }

            // CORREÇÃO: Verificar diretamente o tipo do movimento
            if ($movementType->type == 'in') {
                // 4️⃣ Obter valores do último lançamento
                $lastQuantityTotal = $lastMovement->quantity_total ?? 0;
                // 5️⃣ Calcular novos valores (ENTRADA: soma)
                $newQuantityTotal = $lastQuantityTotal + $validated['quantity_movement'];
                // 6️⃣ Atualizar os valores validados
                $validated['quantity_total'] = $newQuantityTotal;
            } elseif ($movementType->type == 'out') {
                // 4️⃣ Obter valores do último lançamento
                $lastQuantityTotal = $lastMovement->quantity_total ?? 0;
                // 5️⃣ Calcular novos valores (SAÍDA: subtrai)
                $newQuantityTotal = $lastQuantityTotal - $validated['quantity_movement'];
                // 6️⃣ Atualizar os valores validados
                $validated['quantity_total'] = $newQuantityTotal;
            }

            if ($validated['quantity_total'] < 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insuficient Saldo!',
                ], 404);
            }

            // CORREÇÃO: Usar $validated em vez de $validator->validated()
            $movement = InventoryMovementsModel::create($validated);

            DB::commit();

            // Carrega relações úteis para retorno
            $movement->load(['product', 'warehouse', 'company', 'moviment_type']);

            return response()->json([
                'success' => true,
                'data' => $movement,
                'message' => 'Inventory movement created successfully.',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error creating inventory movement.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }





















    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $movement = InventoryMovementsModel::withTrashed()
                ->with(['product', 'warehouse', 'company', 'rental', 'sale'])
                ->find($id);

            if (!$movement) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inventory movement not found.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $movement,
                'message' => 'Inventory movement retrieved successfully.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving inventory movement.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $movement = InventoryMovementsModel::find($id);

            if (!$movement) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inventory movement not found.'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'product_id' => 'sometimes|integer|exists:products,id',
                'warehouse_id' => 'sometimes|integer|exists:warehouses,id',
                'movement_type' => 'sometimes|in:entry,exit,adjustment,transfer',
                'rental_rental_id' => 'nullable|integer|exists:rentals,id',
                'sale_sale_id' => 'nullable|integer|exists:sales,id',
                'quantity_movement' => 'sometimes|numeric|min:0.01',
                'quantity_total' => 'sometimes|numeric|min:0',
                'notes' => 'nullable|string|max:500',
                'company_id' => 'sometimes|integer|exists:companies,id',
                'status' => 'sometimes|in:active,inactive,pending'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $movement->update($validator->validated());

            DB::commit();

            // Carrega as relações atualizadas
            $movement->load(['product', 'warehouse', 'company']);

            return response()->json([
                'success' => true,
                'data' => $movement,
                'message' => 'Inventory movement updated successfully.'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error updating inventory movement.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $movement = InventoryMovementsModel::find($id);

            if (!$movement) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inventory movement not found.'
                ], 404);
            }

            $movement->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Inventory movement deleted successfully.'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error deleting inventory movement.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore the specified soft deleted resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore($id): JsonResponse
    {
        try {
            $movement = InventoryMovementsModel::withTrashed()->find($id);

            if (!$movement) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inventory movement not found.'
                ], 404);
            }

            if (!$movement->trashed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inventory movement is not deleted.'
                ], 400);
            }

            $movement->restore();

            return response()->json([
                'success' => true,
                'data' => $movement,
                'message' => 'Inventory movement restored successfully.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error restoring inventory movement.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get movements by product
     *
     * @param  int  $productId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByProduct($productId): JsonResponse
    {
        try {
            $movements = InventoryMovementsModel::with(['warehouse', 'company'])
                ->byProduct($productId)
                ->active()
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $movements,
                'message' => 'Product movements retrieved successfully.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving product movements.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get movements by warehouse
     *
     * @param  int  $warehouseId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByWarehouse($warehouseId): JsonResponse
    {
        try {
            $movements = InventoryMovementsModel::with(['product', 'company'])
                ->byWarehouse($warehouseId)
                ->active()
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $movements,
                'message' => 'Warehouse movements retrieved successfully.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving warehouse movements.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current stock by product and warehouse
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStock(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|integer|exists:products,id',
                'warehouse_id' => 'required|integer|exists:warehouses,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $stock = InventoryMovementsModel::where('product_id', $request->product_id)
                ->where('warehouse_id', $request->warehouse_id)
                ->where('status', 'active')
                ->orderBy('created_at', 'desc')
                ->first();

            $currentStock = $stock ? $stock->quantity_total : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'product_id' => $request->product_id,
                    'warehouse_id' => $request->warehouse_id,
                    'current_stock' => $currentStock,
                    'last_movement' => $stock
                ],
                'message' => 'Stock retrieved successfully.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving stock.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}