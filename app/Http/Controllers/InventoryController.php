<?php

namespace App\Http\Controllers;

use App\Models\ProductModel;
use App\Models\InventoryModel;
use App\Models\MovementTypeModel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     * @param  Request  $request
     */



     public function index(Request $request)
     {
         try {
             $user = $request->user();
             $companyId = $user->company_id ?? null;
     
             if (!$companyId) {
                 return response()->json([
                     'error' => true,
                     'message' => 'Empresa não identificada para o usuário autenticado.'
                 ], 400);
             }
     
             $limit = (int) $request->query('limit', 25);
             $page = (int) $request->query('page', 1);
             $search = trim($request->query('search', ''), "\"'");
             $productId = $request->query('product_id');
             $quantityBelow = $request->query('quantity_below');
     
             // 🔹 Busca todos os movimentos da empresa
             $query = InventoryModel::with([
                 'product.category',
                 'product.unit',
                 'warehouse',
                 'movement_type'
             ])->where('company_id', $companyId);
     
             if (!empty($productId)) {
                 $query->where('product_id', $productId);
             }
     
             if (!empty($search)) {
                 $query->whereHas('product', function ($q) use ($search) {
                     $q->where('name', 'LIKE', "%{$search}%")
                         ->orWhere('description', 'LIKE', "%{$search}%");
                 });
             }
     
             // 🔸 Aqui usamos get() (sem paginate) para agrupar corretamente
             $allMovements = $query->orderBy('product_id')->get();
     
             // 🔹 Agrupa por produto
             $grouped = $allMovements
                 ->groupBy('product_id')
                 ->map(function ($items) {
                     $first = $items->first();
                     $totalQuantity = $items->sum('quantity_movement');
     
                     $warehouses = $items->groupBy('warehouse_id')->map(function ($warehouseItems) {
                         $w = $warehouseItems->first()->warehouse;
     
                         return [
                             'warehouse' => [
                                 'id' => $w->id ?? null,
                                 'name' => $w->name ?? 'Desconhecido',
                                 'note' => $w->note ?? null,
                             ],
                             'quantity' => number_format($warehouseItems->sum('quantity_movement'), 2, '.', ''),
                         ];
                     })->values();
     
                     $product = $first->product;
     
                     return [
                         'id' => $first->id,
                         'quantity' => number_format($totalQuantity, 2, '.', ''),
                         'updated_at' => $first->updated_at,
                         'created_at' => $first->created_at,
                         'product' => $product ? [
                             'id' => $product->id,
                             'name' => $product->name,
                             'description' => $product->description,
                             'category' => $product->category ? [
                                 'id' => $product->category->id,
                                 'name' => $product->category->name,
                             ] : null,
                             'unit' => $product->unit ? [
                                 'id' => $product->unit->id,
                                 'symbol' => $product->unit->symbol,
                                 'description' => $product->unit->description,
                             ] : null,
                         ] : null,
                         'movement_type' => $first->movement_type,
                         'quantity_per_warehouses' => $warehouses,
                     ];
                 })
                 ->filter(function ($item) use ($quantityBelow) {
                     if (!empty($quantityBelow)) {
                         return (float) $item['quantity'] < (float) $quantityBelow;
                     }
                     return true;
                 })
                 ->values();
     
             // 🔸 Paginação manual após agrupar
             $total = $grouped->count();
             $offset = ($page - 1) * $limit;
             $paged = $grouped->slice($offset, $limit)->values();
     
             return response()->json([
                 'data' => $paged,
                 'pagination' => [
                     'page' => $page,
                     'limit' => $limit,
                     'page_count' => ceil($total / $limit),
                     'total_count' => $total,
                 ],
             ], 200);
     
         } catch (\Exception $e) {
             return response()->json([
                 'error' => true,
                 'message' => 'Erro ao listar o estoque: ' . $e->getMessage(),
             ], 500);
         }
     }
     






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
            $lastMovement = InventoryModel::where('product_id', $validated['product_id'])
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
            $movement = InventoryModel::create($validated);

            DB::commit();

            // Carrega relações úteis para retorno
            $movement->load(['product', 'warehouse', 'company', 'movement_type']);

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

    public function show($id): JsonResponse
    {
        try {
            $movement = InventoryModel::withTrashed()
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

    public function update(Request $request, $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $movement = InventoryModel::find($id);

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

    public function destroy($id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $movement = InventoryModel::find($id);

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
}