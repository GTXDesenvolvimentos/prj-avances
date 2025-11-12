<?php

namespace App\Http\Controllers;

use App\Models\ProductModel;
use App\Models\InventoryModel;
use App\Models\MovementTypeModel;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    use ApiResponser;

    /**
     * Lista o estoque agrupado por produto e armazém.
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
                'movement_type' // importante para saber se é 'in' ou 'out'
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

            // 🔸 Pegamos todos os registros (sem paginate) para agrupar corretamente
            $allMovements = $query->orderBy('product_id')->get();

           // dd($allMovements);



            // 🔹 Agrupa por produto
            $grouped = $allMovements
                ->groupBy('product_id')
                ->map(function ($items) {
                    $first = $items->first();



                    // 🔹 Soma total considerando o tipo de movimento (entrada ou saída)
                    $totalQuantity = $items->sum(function ($movement) {
                        return $movement->movementType->type === 'in'
                            ? $movement->quantity_movement
                            : -$movement->quantity_movement;
                    });

                    // 🔹 Agrupa também por armazém (warehouse)
                    $warehouses = $items->groupBy('warehouse_id')->map(function ($warehouseItems) {
                        $w = $warehouseItems->first()->warehouse;

                        $warehouseQuantity = $warehouseItems->sum(function ($movement) {
                            return $movement->movementType->type === 'in'
                                ? $movement->quantity_movement
                                : -$movement->quantity_movement;
                        });

                        return [
                            'warehouse' => [
                                'id' => $w->id ?? null,
                                'name' => $w->name ?? 'Desconhecido',
                                'note' => $w->note ?? null,
                            ],
                            'quantity' => number_format($warehouseQuantity, 2, '.', ''),
                        ];
                    })->values();

                    $product = $first->product;

                    return [
                        'id' => $first->id,
                        'quantity' => number_format($totalQuantity, 2, '.', ''), // ✅ saldo real do produto
                        'updated_at' => $first->updated_at,
                        'created_at' => $first->created_at,
                        'product' => $product, 
                        'movement_type' => $first->movement_type,
                        'quantity_per_warehouses' => $warehouses,
                    ];
                })
                // 🔹 Aplica filtro opcional (estoques abaixo de determinado valor)
                ->filter(function ($item) use ($quantityBelow) {
                    if (!empty($quantityBelow)) {
                        return (float) $item['quantity_total'] < (float) $quantityBelow;
                    }
                    return true;
                })
                ->values();

            // 🔸 Paginação manual após o agrupamento
            $total = $grouped->count();
            $offset = ($page - 1) * $limit;
            $paged = $grouped->slice($offset, $limit)->values();

             return response()->json([
                'data' => $paged,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total_pages' => ceil($total / $limit),
                    'total' => $total,
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
