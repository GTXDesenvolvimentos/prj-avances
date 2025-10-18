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

}