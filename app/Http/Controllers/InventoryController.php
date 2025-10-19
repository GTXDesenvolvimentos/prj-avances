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

            // 🔸 Pega todos os movimentos ordenados por data
            $allMovements = $query->orderBy('created_at', 'desc')->get();

            // 🔹 Agrupa primeiro por data (Y-m-d)
            $grouped = $allMovements
                ->groupBy(function ($item) {
                    return \Carbon\Carbon::parse($item->created_at)->format('Y-m-d');
                })
                ->map(function ($items, $date) {
                    // Dentro de cada data, agrupa por produto
                    $products = $items->groupBy('product_id')->map(function ($productItems) {
                        $first = $productItems->first();
                        $product = $first->product;
                        $totalQuantity = $productItems->sum('quantity_movement');

                        $warehouses = $productItems->groupBy('warehouse_id')->map(function ($warehouseItems) {
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

                        return [
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
                            'quantity' => number_format($totalQuantity, 2, '.', ''),
                            'movement_type' => $first->movement_type,
                            'quantity_per_warehouses' => $warehouses,
                        ];
                    })->values();

                    return [
                        'date' => $date,
                        'products' => $products,
                    ];
                })
                ->sortKeysDesc() // 🔹 mais recentes primeiro
                ->values();

            // 🔸 Filtro opcional por quantidade mínima
            if (!empty($quantityBelow)) {
                $grouped = $grouped->map(function ($group) use ($quantityBelow) {
                    $filteredProducts = collect($group['products'])
                        ->filter(fn($p) => (float) $p['quantity'] < (float) $quantityBelow)
                        ->values();

                    return [
                        'date' => $group['date'],
                        'products' => $filteredProducts,
                    ];
                })->filter(fn($g) => $g['products']->isNotEmpty())->values();
            }

            // 🔸 Paginação manual
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