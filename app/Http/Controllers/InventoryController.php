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
    public function index(Request $request): JsonResponse
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

            // 🔹 Parâmetros de consulta
            $limit = (int) $request->query('limit', 25);
            $page = (int) $request->query('page', 1);
            $search = trim($request->query('search', ''), "\"'");
            $productId = $request->query('product_id');
            $quantityBelow = $request->query('quantity_below');

            // 🔹 Monta query base
            $query = InventoryModel::with([
                'product.category',
                'product.unit',
                'warehouse',
                'movementType', // relação correta
            ])->where('company_id', $companyId);

            // 🔹 Filtros opcionais
            if (!empty($productId)) {
                $query->where('product_id', $productId);
            }

            if (!empty($search)) {
                $query->whereHas('product', function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            // 🔸 Busca todos os registros para agrupar
            $allMovements = $query->orderBy('product_id')->get();

            // 🔹 Agrupa por produto
            $grouped = $allMovements
                ->groupBy('product_id')
                ->map(function ($items) {
                    $first = $items->first();

                    // 🔹 Soma total considerando o tipo de movimento (entrada/saída)
                    $totalQuantity = $items->sum(function ($movement) {
                        $type = $movement->movementType?->type; // safe access
                        return match ($type) {
                            'in' => $movement->quantity_movement,
                            'out' => -$movement->quantity_movement,
                            default => 0,
                        };
                    });

                    // 🔹 Agrupa também por armazém
                    $warehouses = $items->groupBy('warehouse_id')->map(function ($warehouseItems) {
                        $warehouse = $warehouseItems->first()->warehouse;

                        $warehouseQuantity = $warehouseItems->sum(function ($movement) {
                            $type = $movement->movementType?->type;
                            return match ($type) {
                                'in' => $movement->quantity_movement,
                                'out' => -$movement->quantity_movement,
                                default => 0,
                            };
                        });

                        return [
                            'warehouse' => [
                                'id' => $warehouse->id ?? null,
                                'name' => $warehouse->name ?? 'Desconhecido',
                                'note' => $warehouse->note ?? null,
                            ],
                            'quantity' => number_format($warehouseQuantity, 2, '.', ''),
                        ];
                    })->values();

                    $product = $first->product;

                    return [
                        'id' => $first->id,
                        'quantity' => number_format($totalQuantity, 2, '.', ''), // saldo final
                        'updated_at' => $first->updated_at,
                        'created_at' => $first->created_at,
                        'product' => $product ? [
                            'id' => $product->id,
                            'product_code' => $product->product_code,
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
                        'movement_type' => $first->movementType ? [
                            'id' => $first->movementType->id,
                            'name' => $first->movementType->name ?? null,
                            'type' => $first->movementType->type ?? null,
                        ] : null,
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

            // 🔸 Paginação manual
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
