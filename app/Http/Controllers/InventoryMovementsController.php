<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponser;
use App\Models\InventoryMovementsModel;
use App\Models\MovementTypeModel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class InventoryMovementsController extends Controller
{
    use ApiResponser;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        return $this->apiTryCatch(function () use ($request) {
            $user = $request->user();
            $companyId = $user->company_id;

            // Parâmetros de consulta
            $limit = (int) $request->query('limit', 25);
            $search = trim($request->query('search', ''), '"\'');
            $product_id = $request->query('product_id');
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');

            // Query base com as relações
            $query = InventoryMovementsModel::with(['product', 'movement_type', 'warehouse', 'company'])
                ->where('company_id', $companyId);

            // Filtro por busca (exemplo: nome do produto)
            if (!empty($search)) {
                $query->whereHas('product', function ($q) use ($search) {
                    $q->where('product_name', 'like', "%{$search}%");
                });
            }

            // Filtro por id do produto
            if (!empty($product_id)) {
                $query->whereHas('product', function ($q) use ($product_id) {
                    $q->where('id', '=', "$product_id");
                });
            }

            // Filtro por data inicial
            if (!empty($startDate)) {
                $query->whereDate('created_at', '>=', $startDate);
            }

            // Filtro por data final
            if (!empty($endDate)) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            // Ordenação (mais recentes primeiro)
            $query->orderBy('id', 'desc');

            // Paginação
            $movements = $query->paginate($limit);

            return $this->paginatedResponse($movements, 'Inventory movements retrieved successfully');
        });
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        return $this->apiTryCatch(function () use ($request) {
            $user = $request->user();

            if (!$user->company_id) {
                return $this->errorResponse(
                    'Usuário não está vinculado a nenhuma empresa.',
                    'FORBIDDEN',
                    403
                );
            }

            DB::beginTransaction();

            // Injeta company_id do usuário autenticado antes da validação
            $request->merge(['company_id' => $user->company_id]);

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
                return $this->validationErrorResponse($validator->errors()->toArray());
            }

            $validated = $validator->validated();

            // Buscar o tipo de movimento no banco
            $movementType = MovementTypeModel::find($validated['movement_type']);
            $lastMovement = InventoryMovementsModel::where('product_id', $validated['product_id'])
                ->where('warehouse_id', $validated['warehouse_id'])
                ->orderBy('id', 'desc')
                ->first();

            if (!$movementType) {
                return $this->errorResponse(
                    'Movement type not found.',
                    'NOT_FOUND',
                    404
                );
            }

            // Verificar diretamente o tipo do movimento
            if ($movementType->type == 'in') {
                // Obter valores do último lançamento
                $lastQuantityTotal = $lastMovement->quantity_total ?? 0;
                // Calcular novos valores (ENTRADA: soma)
                $newQuantityTotal = $lastQuantityTotal + $validated['quantity_movement'];
                // Atualizar os valores validados
                $validated['quantity_total'] = $newQuantityTotal;
            } elseif ($movementType->type == 'out') {
                // Obter valores do último lançamento
                $lastQuantityTotal = $lastMovement->quantity_total ?? 0;
                // Calcular novos valores (SAÍDA: subtrai)
                $newQuantityTotal = $lastQuantityTotal - $validated['quantity_movement'];
                // Atualizar os valores validados
                $validated['quantity_total'] = $newQuantityTotal;
            }

            if ($validated['quantity_total'] < 0) {
                return $this->errorResponse(
                    'Insuficient Saldo!',
                    'INSUFFICIENT_STOCK',
                    422
                );
            }

            // Adicionar created_by e updated_by
            $validated['created_by'] = $user->id;
            $validated['updated_by'] = $user->id;

            // Criar o movimento
            $movement = InventoryMovementsModel::create($validated);

            DB::commit();

            // Carrega relações úteis para retorno
            $movement->load(['product', 'warehouse', 'company', 'movement_type']);

            return $this->createdResponse($movement, 'Inventory movement created successfully');
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {
            $user = $request->user();

            $movement = InventoryMovementsModel::withTrashed()
                ->with(['product', 'warehouse', 'company', 'rental', 'sale'])
                ->where('company_id', $user->company_id)
                ->find($id);

            if (!$movement) {
                return $this->errorResponse(
                    'Inventory movement not found.',
                    'NOT_FOUND',
                    404
                );
            }

            return $this->successResponse($movement, 'Inventory movement retrieved successfully');
        });
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {
            $user = $request->user();

            DB::beginTransaction();

            $movement = InventoryMovementsModel::where('company_id', $user->company_id)
                ->find($id);

            if (!$movement) {
                return $this->errorResponse(
                    'Inventory movement not found.',
                    'NOT_FOUND',
                    404
                );
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
                return $this->validationErrorResponse($validator->errors()->toArray());
            }

            $data = $validator->validated();
            $data['updated_by'] = $user->id;

            $movement->update($data);

            DB::commit();

            // Carrega as relações atualizadas
            $movement->load(['product', 'warehouse', 'company']);

            return $this->updatedResponse($movement, 'Inventory movement updated successfully');
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {
            $user = $request->user();

            DB::beginTransaction();

            $movement = InventoryMovementsModel::where('company_id', $user->company_id)
                ->find($id);

            if (!$movement) {
                return $this->errorResponse(
                    'Inventory movement not found.',
                    'NOT_FOUND',
                    404
                );
            }

            $movement->update([
                'deleted_by' => $user->id
            ]);

            $movement->delete();

            DB::commit();

            return $this->deletedResponse(
                'Inventory movement deleted successfully',
                ['id' => $movement->id, 'deleted_at' => $movement->deleted_at]
            );
        });
    }

    /**
     * Restore the specified soft deleted resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {
            $user = $request->user();

            $movement = InventoryMovementsModel::withTrashed()
                ->where('company_id', $user->company_id)
                ->find($id);

            if (!$movement) {
                return $this->errorResponse(
                    'Inventory movement not found.',
                    'NOT_FOUND',
                    404
                );
            }

            if (!$movement->trashed()) {
                return $this->errorResponse(
                    'Inventory movement is not deleted.',
                    'NOT_DELETED',
                    400
                );
            }

            $movement->restore();

            $movement->update([
                'deleted_by' => null,
                'updated_by' => $user->id
            ]);

            return $this->successResponse($movement, 'Inventory movement restored successfully');
        });
    }

    /**
     * Get movements by product
     *
     * @param  int  $productId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByProduct(Request $request, $productId)
    {
        return $this->apiTryCatch(function () use ($request, $productId) {
            $user = $request->user();

            $movements = InventoryMovementsModel::with(['warehouse', 'company'])
                ->where('product_id', $productId)
                ->where('company_id', $user->company_id)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->successResponse($movements, 'Product movements retrieved successfully');
        });
    }

    /**
     * Get movements by warehouse
     *
     * @param  int  $warehouseId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByWarehouse(Request $request, $warehouseId)
    {
        return $this->apiTryCatch(function () use ($request, $warehouseId) {
            $user = $request->user();

            $movements = InventoryMovementsModel::with(['product', 'company'])
                ->where('warehouse_id', $warehouseId)
                ->where('company_id', $user->company_id)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->successResponse($movements, 'Warehouse movements retrieved successfully');
        });
    }

    /**
     * Get current stock by product and warehouse
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStock(Request $request)
    {
        return $this->apiTryCatch(function () use ($request) {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'product_id' => 'required|integer|exists:products,id',
                'warehouse_id' => 'required|integer|exists:warehouses,id'
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors()->toArray());
            }

            $stock = InventoryMovementsModel::where('product_id', $request->product_id)
                ->where('warehouse_id', $request->warehouse_id)
                ->where('company_id', $user->company_id)
                ->orderBy('created_at', 'desc')
                ->first();

            $currentStock = $stock ? $stock->quantity_total : 0;

            return $this->successResponse([
                'product_id' => $request->product_id,
                'warehouse_id' => $request->warehouse_id,
                'current_stock' => $currentStock,
                'last_movement' => $stock
            ], 'Stock retrieved successfully');
        });
    }
}