<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovementsModel;
use App\Models\MovementTypeModel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Inventory Movements",
 *     description="Gerenciamento dos movimentos de estoque (entradas, saídas, ajustes, transferências)"
 * )
 *
 * @OA\Schema(
 *     schema="InventoryMovement",
 *     title="Inventory Movement",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="product_id", type="integer", example=5),
 *     @OA\Property(property="warehouse_id", type="integer", example=2),
 *     @OA\Property(property="movement_type", type="integer", example=1, description="ID do tipo de movimento"),
 *     @OA\Property(property="quantity_movement", type="number", example=10.5),
 *     @OA\Property(property="quantity_total", type="number", example=100.0),
 *     @OA\Property(property="notes", type="string", example="Entrada de produto"),
 *     @OA\Property(property="status", type="string", example="active"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-10-21T12:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-10-21T12:30:00Z"),
 *     @OA\Property(
 *         property="product",
 *         type="object",
 *         @OA\Property(property="id", type="integer", example=5),
 *         @OA\Property(property="name", type="string", example="Produto Exemplo")
 *     ),
 *     @OA\Property(
 *         property="warehouse",
 *         type="object",
 *         @OA\Property(property="id", type="integer", example=2),
 *         @OA\Property(property="name", type="string", example="Depósito Central")
 *     ),
 *     @OA\Property(
 *         property="movement_type_detail",
 *         type="object",
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="Entrada")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="Pagination",
 *     title="Pagination Metadata",
 *     type="object",
 *     @OA\Property(property="page", type="integer", example=1),
 *     @OA\Property(property="limit", type="integer", example=25),
 *     @OA\Property(property="page_count", type="integer", example=10),
 *     @OA\Property(property="total_count", type="integer", example=250)
 * )
 */
class InventoryMovementsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/inventory-movements",
     *     summary="Listar movimentos de estoque",
     *     description="Retorna todos os movimentos de estoque filtrados por produto, data, etc.",
     *     tags={"Inventory Movements"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="limit", in="query", description="Limite por página", @OA\Schema(type="integer", example=25)),
     *     @OA\Parameter(name="search", in="query", description="Busca pelo nome do produto", @OA\Schema(type="string", example="Parafuso")),
     *     @OA\Parameter(name="product_id", in="query", description="Filtrar por ID do produto", @OA\Schema(type="integer", example=5)),
     *     @OA\Parameter(name="start_date", in="query", description="Data inicial (YYYY-MM-DD)", @OA\Schema(type="string", example="2025-10-01")),
     *     @OA\Parameter(name="end_date", in="query", description="Data final (YYYY-MM-DD)", @OA\Schema(type="string", example="2025-10-20")),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de movimentos retornada com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/InventoryMovement")),
     *             @OA\Property(property="pagination", ref="#/components/schemas/Pagination")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Erro interno ao listar movimentos")
     * )
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $companyId = $user->company_id;

            $limit = (int) $request->query('limit', 25);
            $search = trim($request->query('search', ''), '"\'');
            $product_id = $request->query('product_id');
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');

            $query = InventoryMovementsModel::with(['product', 'movement_type', 'warehouse', 'company'])
                ->where('company_id', $companyId);

            if (!empty($search)) {
                $query->whereHas('product', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            }

            if (!empty($product_id)) {
                $query->where('product_id', $product_id);
            }

            if (!empty($startDate)) {
                $query->whereDate('created_at', '>=', $startDate);
            }

            if (!empty($endDate)) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            $movements = $query->orderBy('id', 'desc')->paginate($limit);

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
                'message' => 'Erro ao buscar movimentos de inventário.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/inventory-movements",
     *     summary="Registrar novo movimento de estoque",
     *     tags={"Inventory Movements"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/InventoryMovement")
     *     ),
     *     @OA\Response(response=201, description="Movimento criado com sucesso", @OA\JsonContent(ref="#/components/schemas/InventoryMovement")),
     *     @OA\Response(response=422, description="Erro de validação"),
     *     @OA\Response(response=500, description="Erro interno ao criar movimento")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        // ... (mantém seu código atual)
        // Nenhuma alteração de lógica necessária.
    }

    /**
     * @OA\Get(
     *     path="/api/inventory-movements/{id}",
     *     summary="Exibir um movimento específico",
     *     tags={"Inventory Movements"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID do movimento", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Movimento encontrado", @OA\JsonContent(ref="#/components/schemas/InventoryMovement")),
     *     @OA\Response(response=404, description="Movimento não encontrado")
     * )
     */
    public function show($id): JsonResponse
    {
        // ... mantém sua lógica atual
    }

    /**
     * @OA\Put(
     *     path="/api/inventory-movements/{id}",
     *     summary="Atualizar movimento de estoque",
     *     tags={"Inventory Movements"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(@OA\JsonContent(ref="#/components/schemas/InventoryMovement")),
     *     @OA\Response(response=200, description="Atualizado com sucesso"),
     *     @OA\Response(response=422, description="Erro de validação"),
     *     @OA\Response(response=404, description="Não encontrado")
     * )
     */
    public function update(Request $request, $id): JsonResponse
    {
        // ... mantém sua lógica atual
    }

    /**
     * @OA\Delete(
     *     path="/api/inventory-movements/{id}",
     *     summary="Excluir movimento de estoque",
     *     tags={"Inventory Movements"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Movimento excluído com sucesso"),
     *     @OA\Response(response=404, description="Movimento não encontrado")
     * )
     */
    public function destroy($id): JsonResponse
    {
        // ... mantém sua lógica atual
    }

    /**
     * @OA\Patch(
     *     path="/api/inventory-movements/{id}/restore",
     *     summary="Restaurar movimento excluído (soft delete)",
     *     tags={"Inventory Movements"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Restaurado com sucesso"),
     *     @OA\Response(response=404, description="Movimento não encontrado")
     * )
     */
    public function restore($id): JsonResponse
    {
        // ... mantém sua lógica atual
    }

    /**
     * @OA\Get(
     *     path="/api/inventory-movements/product/{productId}",
     *     summary="Listar movimentos por produto",
     *     tags={"Inventory Movements"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Movimentos retornados", @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/InventoryMovement")))
     * )
     */
    public function getByProduct($productId): JsonResponse
    {
        // ... mantém sua lógica atual
    }

    /**
     * @OA\Get(
     *     path="/api/inventory-movements/warehouse/{warehouseId}",
     *     summary="Listar movimentos por armazém",
     *     tags={"Inventory Movements"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="warehouseId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Movimentos retornados", @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/InventoryMovement")))
     * )
     */
    public function getByWarehouse($warehouseId): JsonResponse
    {
        // ... mantém sua lógica atual
    }

    /**
     * @OA\Post(
     *     path="/api/inventory-movements/stock",
     *     summary="Obter saldo atual por produto e armazém",
     *     tags={"Inventory Movements"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id", "warehouse_id"},
     *             @OA\Property(property="product_id", type="integer", example=5),
     *             @OA\Property(property="warehouse_id", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Saldo retornado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="product_id", type="integer", example=5),
     *                 @OA\Property(property="warehouse_id", type="integer", example=2),
     *                 @OA\Property(property="current_stock", type="number", example=150.25)
     *             )
     *         )
     *     )
     * )
     */
    public function getStock(Request $request): JsonResponse
    {
        // ... mantém sua lógica atual
    }
}
