<?php
/**
 * @OA\Tag(
 *     name="Product Units",
 *     description="Endpoints de Unidades de Medida"
 * )
 */


namespace App\Http\Controllers;

use App\Traits\ApiResponser;
use App\Models\ProductUnitsModel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductUnitsController extends Controller
{
    use ApiResponser;

    /**
     * @OA\Get(
     *     path="/api/product-units",
     *     tags={"Product Units"},
     *     summary="Listar unidades de medida",
     *     description="Retorna a lista paginada de unidades de medida da empresa do usuário autenticado.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Número de registros por página (default: 25)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Busca por símbolo ou descrição",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filtra pelo status da unidade",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de unidades recuperada com sucesso"
     *     ),
     *     @OA\Response(response=401, description="Não autorizado"),
     *     @OA\Response(response=500, description="Erro interno do servidor")
     * )
     */
    public function index(Request $request)
    {
        return $this->apiTryCatch(function () use ($request) {

            $user = $request->user();
            $limit = (int) $request->query('limit', 25);
            $search = trim($request->query('search', ''), '"\'');

            $query = ProductUnitsModel::with([
                'company' => function ($q) {
                    $q->withTrashed();
                }
            ])->where('company_id', $user->company_id);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('symbol', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%")
                        ->withTrashed();
                });
            }

            if ($request->filled('status')) {
                $query->where('status', $request->query('status'));
            }

            $query->orderBy('created_at', 'desc');
            $units = $query->paginate($limit);

            return $this->paginatedResponse($units, 'Product units retrieved successfully');
        });
    }

    /**
     * @OA\Post(
     *     path="/api/product-units",
     *     tags={"Product Units"},
     *     summary="Criar uma nova unidade de medida",
     *     description="Cria uma nova unidade de medida associada à empresa do usuário autenticado.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"symbol","description"},
     *             @OA\Property(property="symbol", type="string", example="kg"),
     *             @OA\Property(property="description", type="string", example="Kilograma")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Unidade criada com sucesso"),
     *     @OA\Response(response=400, description="Erro de validação"),
     *     @OA\Response(response=401, description="Não autorizado")
     * )
     */
    public function store(Request $request)
    {
        return $this->apiTryCatch(function () use ($request) {
            $user = $request->user();
            $data = $request->all();
            $data['company_id'] = $user->company_id;

            $validator = Validator::make($data, [
                'symbol' => 'required|string|min:1|unique:product_units,symbol,NULL,NULL,company_id,' . $user->company_id,
                'description' => 'required|string|min:4',
                'company_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors()->toArray());
            }

            $data = [
                'symbol' => $request->symbol,
                'description' => $request->description,
                'company_id' => $user->company_id,
                "created_by" => $user->id,
                "updated_by" => $user->id
            ];

            $unit = ProductUnitsModel::create($data);
            return $this->createdResponse($unit, 'Product unit created successfully');
        });
    }

    /**
     * @OA\Get(
     *     path="/api/product-units/{id}",
     *     tags={"Product Units"},
     *     summary="Visualizar detalhes de uma unidade de medida",
     *     description="Retorna os dados de uma unidade de medida específica.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID da unidade de medida",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Unidade recuperada com sucesso"),
     *     @OA\Response(response=404, description="Unidade não encontrada"),
     *     @OA\Response(response=401, description="Não autorizado")
     * )
     */
    public function show(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {

            $user = $request->user();

            $unit = ProductUnitsModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$unit) {
                return $this->errorResponse(
                    'Product unit not found',
                    'NOT_FOUND',
                    404
                );
            }

            return $this->successResponse(
                $unit,
                'Product unit retrieved successfully'
            );
        });
    }

    /**
     * @OA\Put(
     *     path="/api/product-units/{id}",
     *     tags={"Product Units"},
     *     summary="Atualizar unidade de medida",
     *     description="Atualiza uma unidade de medida existente.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID da unidade de medida",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"symbol","description"},
     *             @OA\Property(property="symbol", type="string", example="L"),
     *             @OA\Property(property="description", type="string", example="Litro")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Unidade atualizada com sucesso"),
     *     @OA\Response(response=404, description="Unidade não encontrada"),
     *     @OA\Response(response=400, description="Erro de validação"),
     *     @OA\Response(response=401, description="Não autorizado")
     * )
     */
    public function update(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {

            $user = $request->user();
            $data = $request->all();
            $data['company_id'] = $user->company_id;

            $unit = ProductUnitsModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$unit) {
                return $this->errorResponse(
                    'Product unit not found or not accessible for this user.',
                    'NOT_FOUND',
                    404
                );
            }

            $validator = Validator::make($data, [
                'symbol' => 'required|string|min:1|unique:product_units,symbol,NULL,NULL,company_id,' . $user->company_id,
                'description' => 'required|string|min:4',
                'company_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors()->toArray());
            }

            $unit->update([
                'symbol' => $data['symbol'] ?? $unit->symbol,
                'description' => $data['description'] ?? $unit->description,
                'updated_by' => $user->id
            ]);

            return $this->updatedResponse($unit, 'Product unit updated successfully');
        });
    }

    /**
     * @OA\Delete(
     *     path="/api/product-units/{id}",
     *     tags={"Product Units"},
     *     summary="Excluir unidade de medida",
     *     description="Remove (soft delete) uma unidade de medida da empresa do usuário autenticado.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID da unidade de medida",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="Unidade excluída com sucesso"),
     *     @OA\Response(response=404, description="Unidade não encontrada"),
     *     @OA\Response(response=401, description="Não autorizado")
     * )
     */
    public function destroy(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {

            $user = $request->user();

            $unit = ProductUnitsModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$unit) {
                return $this->errorResponse(
                    'Product unit not found or not accessible for this user.',
                    'NOT_FOUND',
                    404
                );
            }

            $unit->delete();

            return $this->deletedResponse('Product unit deleted successfully', [
                'id' => $unit->id,
                'deleted_at' => $unit->deleted_at
            ]);
        });
    }
}
