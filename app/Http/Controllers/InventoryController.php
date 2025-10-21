<?php

namespace App\Http\Controllers;

use App\Models\ProductModel;
use App\Models\InventoryModel;
use App\Models\MovementTypeModel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Inventory",
 *     description="Endpoints para controle e visualização de estoque"
 * )
 */

/**
 * @OA\Schema(
 *     schema="Inventory",
 *     title="Inventory",
 *     description="Modelo de inventário (movimentos de estoque)",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="product_id", type="integer", example=5),
 *     @OA\Property(property="warehouse_id", type="integer", example=2),
 *     @OA\Property(property="company_id", type="integer", example=10),
 *     @OA\Property(property="quantity_movement", type="number", format="float", example=25.50),
 *     @OA\Property(property="quantity_total", type="number", format="float", example=120.00),
 *     @OA\Property(property="movement_type", type="string", example="in"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-10-21T12:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-10-21T12:30:00Z"),
 *     @OA\Property(
 *         property="product",
 *         type="object",
 *         @OA\Property(property="id", type="integer", example=10),
 *         @OA\Property(property="product_code", type="string", example="PRD-001"),
 *         @OA\Property(property="name", type="string", example="Produto Teste"),
 *         @OA\Property(property="description", type="string", example="Descrição do produto"),
 *         @OA\Property(
 *             property="category",
 *             type="object",
 *             @OA\Property(property="id", type="integer", example=3),
 *             @OA\Property(property="name", type="string", example="Categoria A")
 *         ),
 *         @OA\Property(
 *             property="unit",
 *             type="object",
 *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="symbol", type="string", example="kg"),
 *             @OA\Property(property="description", type="string", example="Quilograma")
 *         )
 *     ),
 *     @OA\Property(
 *         property="quantity_per_warehouses",
 *         type="array",
 *         @OA\Items(
 *             @OA\Property(property="warehouse", type="object",
 *                 @OA\Property(property="id", type="integer", example=2),
 *                 @OA\Property(property="name", type="string", example="Depósito Central"),
 *                 @OA\Property(property="note", type="string", example="Armazém principal")
 *             ),
 *             @OA\Property(property="quantity", type="number", format="float", example=100.50)
 *         )
 *     )
 * )
 */
class InventoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/inventory",
     *     summary="Listar estoque atual (saldo real por produto)",
     *     description="Retorna os saldos de estoque agrupados por produto e armazém, considerando entradas e saídas.",
     *     tags={"Inventory"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Quantidade de registros por página",
     *         required=false,
     *         @OA\Schema(type="integer", example=25)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Número da página para paginação",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Busca pelo nome ou descrição do produto",
     *         required=false,
     *         @OA\Schema(type="string", example="Parafuso")
     *     ),
     *     @OA\Parameter(
     *         name="product_id",
     *         in="query",
     *         description="Filtrar por ID do produto",
     *         required=false,
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *     @OA\Parameter(
     *         name="quantity_below",
     *         in="query",
     *         description="Filtrar produtos com quantidade abaixo de um valor",
     *         required=false,
     *         @OA\Schema(type="number", example=10.0)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de produtos com saldo em estoque",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Inventory")),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="page", type="integer", example=1),
     *                 @OA\Property(property="limit", type="integer", example=25),
     *                 @OA\Property(property="page_count", type="integer", example=5),
     *                 @OA\Property(property="total_count", type="integer", example=120)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Empresa não identificada"),
     *     @OA\Response(response=500, description="Erro interno ao listar o estoque")
     * )
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

            $allMovements = $query->orderBy('product_id')->get();

            $grouped = $allMovements
                ->groupBy('product_id')
                ->map(function ($items) {
                    $first = $items->first();

                    $totalQuantity = $items->sum(function ($movement) {
                        return $movement->movementType->type === 'in'
                            ? $movement->quantity_movement
                            : -$movement->quantity_movement;
                    });

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
                        'quantity' => number_format($totalQuantity, 2, '.', ''),
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
