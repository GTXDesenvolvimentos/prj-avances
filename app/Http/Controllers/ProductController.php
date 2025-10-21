<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;
use App\Models\ProductModel;

/**
 * @OA\Tag(
 *     name="Products",
 *     description="Endpoints de gerenciamento de produtos"
 * )
 */
class ProductController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/products",
     *     summary="Lista produtos",
     *     description="Retorna uma lista paginada de produtos da empresa do usuário autenticado.",
     *     tags={"Products"},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Quantidade de registros por página",
     *         required=false,
     *         @OA\Schema(type="integer", default=25)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Texto de busca (nome, código ou descrição)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista retornada com sucesso"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Não autorizado"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $limit = (int) $request->query('limit', 25);
        $search = trim($request->query('search', ''), '"\'');

        $query = ProductModel::with([
            'category' => fn($q) => $q->withTrashed(),
            'unit' => fn($q) => $q->withTrashed(),
        ])->where('company_id', $user->company_id);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('product_code', 'LIKE', "%{$search}%")
                  ->orWhere('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        foreach (['unit_id', 'category_id', 'status'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->query($field));
            }
        }

        if ($request->filled('availability')) {
            $availabilities = explode(',', $request->query('availability'));
            $query->where(function ($q) use ($availabilities) {
                foreach ($availabilities as $availability) {
                    $q->orWhereRaw('FIND_IN_SET(?, availability)', [$availability]);
                }
            });
        }

        foreach (['is_dynamic_sale_price', 'is_dynamic_rental_price'] as $boolField) {
            if ($request->has($boolField)) {
                $query->where($boolField, (bool) $request->query($boolField));
            }
        }

        $products = $query->paginate($limit);

        return response()->json([
            'success' => true,
            'data' => $products->items(),
            'pagination' => [
                'page' => $products->currentPage(),
                'limit' => $products->perPage(),
                'page_count' => $products->lastPage(),
                'total_count' => $products->total(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/products/{id}",
     *     summary="Exibe um produto específico",
     *     tags={"Products"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID do produto",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Produto encontrado"),
     *     @OA\Response(response=404, description="Produto não encontrado")
     * )
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $product = ProductModel::withTrashed()
            ->with([
                'category' => fn($q) => $q->withTrashed(),
                'unit' => fn($q) => $q->withTrashed(),
            ])
            ->where('id', $id)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => 'Produto não encontrado']
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/products",
     *     summary="Cria um novo produto",
     *     tags={"Products"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"unit_id","category_id","name"},
     *             @OA\Property(property="unit_id", type="integer", example=1),
     *             @OA\Property(property="category_id", type="integer", example=2),
     *             @OA\Property(property="name", type="string", example="Produto exemplo"),
     *             @OA\Property(property="description", type="string", example="Descrição do produto"),
     *             @OA\Property(property="availability", type="array", @OA\Items(type="string", enum={"sale","rental","internal"})),
     *             @OA\Property(property="sale_price", type="number", format="float", example=50.0),
     *             @OA\Property(property="rental_price", type="number", format="float", example=20.0)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Produto criado com sucesso"),
     *     @OA\Response(response=422, description="Erro de validação")
     * )
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->json()->all();

        $validator = Validator::make($data, [
            'unit_id' => 'required|integer',
            'category_id' => 'required|integer',
            'name' => 'required|string|min:2',
            'availability' => 'nullable|array',
            'availability.*' => 'in:sale,rental,internal',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $product = ProductModel::create([
                'unit_id' => $data['unit_id'],
                'category_id' => $data['category_id'],
                'company_id' => $user->company_id,
                'product_code' => $data['product_code'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'availability' => isset($data['availability']) ? implode(',', $data['availability']) : null,
                'average_cost' => $data['average_cost'] ?? 0,
                'sale_price' => $data['sale_price'] ?? 0,
                'rental_price' => $data['rental_price'] ?? 0,
                'is_dynamic_sale_price' => $data['is_dynamic_sale_price'] ?? false,
                'is_dynamic_rental_price' => $data['is_dynamic_rental_price'] ?? false,
            ]);

            return response()->json(['success' => true, 'data' => $product], 201);
        } catch (QueryException $e) {
            return response()->json(['success' => false, 'errors' => ['database' => $e->getMessage()]], 400);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'errors' => ['general' => $e->getMessage()]], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/products/{id}",
     *     summary="Atualiza um produto",
     *     tags={"Products"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="unit_id", type="integer", example=1),
     *             @OA\Property(property="category_id", type="integer", example=2),
     *             @OA\Property(property="name", type="string", example="Produto atualizado"),
     *             @OA\Property(property="description", type="string", example="Nova descrição"),
     *             @OA\Property(property="availability", type="array", @OA\Items(type="string", enum={"sale","rental","internal"})),
     *             @OA\Property(property="sale_price", type="number", format="float", example=45.0),
     *             @OA\Property(property="rental_price", type="number", format="float", example=18.0)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Produto atualizado com sucesso"),
     *     @OA\Response(response=404, description="Produto não encontrado")
     * )
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        $product = ProductModel::where('id', $id)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$product) {
            return response()->json(['success' => false, 'errors' => ['general' => 'Produto não encontrado']], 404);
        }

        $data = $request->json()->all();

        $validator = Validator::make($data, [
            'unit_id' => 'required|integer',
            'category_id' => 'required|integer',
            'name' => 'required|string|min:2',
            'availability' => 'nullable|array',
            'availability.*' => 'in:sale,rental,internal',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $product->update(array_merge($data, [
                'availability' => isset($data['availability'])
                    ? implode(',', $data['availability'])
                    : $product->availability,
            ]));

            return response()->json(['success' => true, 'data' => $product]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'errors' => ['general' => $e->getMessage()]], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/products/{id}",
     *     summary="Remove um produto",
     *     tags={"Products"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Produto removido com sucesso"),
     *     @OA\Response(response=404, description="Produto não encontrado")
     * )
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $product = ProductModel::where('id', $id)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$product) {
            return response()->json(['success' => false, 'errors' => ['general' => 'Produto não encontrado']], 404);
        }

        try {
            $product->delete();

            return response()->json(['success' => true, 'message' => 'Produto removido com sucesso']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'errors' => ['general' => $e->getMessage()]], 500);
        }
    }
}
