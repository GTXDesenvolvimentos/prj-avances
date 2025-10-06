<?php

namespace App\Http\Controllers;

use GuzzleHttp\Psr7\Query;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;
use App\Models\ProductModel;

class ProductController extends Controller
{

    public function index(Request $request)
    {
        $user = $request->user();
        $limit = (int) $request->query('limit', 25);
        $search = trim($request->query('search', ''), '"\'');

        // Aplica withTrashed na query principal
        $query = ProductModel::withTrashed()
            ->with([
                'category' => function ($q) {
                    $q->withTrashed(); // inclui categorias soft deleted
                },
                'unit' => function ($q) {
                    $q->withTrashed(); // inclui unidades soft deleted
                }
            ])
            ->where('company_id', $user->company_id);

        // Filtro de busca
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('product_code', 'LIKE', "%{$search}%")
                    ->orWhere('name', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Filtro por unidade
        if ($request->filled('unit_id')) {
            $query->where('unit_id', $request->query('unit_id'));
        }

        // Filtro por categoria
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        // Filtro por disponibilidade
        if ($request->filled('availability')) {
            $availabilities = explode(',', $request->query('availability'));

            $query->where(function ($q) use ($availabilities) {
                foreach ($availabilities as $availability) {
                    $q->orWhereRaw('FIND_IN_SET(?, availability)', [$availability]);
                }
            });
        }

        // Filtro por preços dinâmicos
        if ($request->has('is_dynamic_sale_price')) {
            $query->where('is_dynamic_sale_price', (bool) $request->query('is_dynamic_sale_price'));
        }

        if ($request->has('is_dynamic_rental_price')) {
            $query->where('is_dynamic_rental_price', (bool) $request->query('is_dynamic_rental_price'));
        }

        // Filtro por status
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        // Paginação
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
        ], 200);
    }


    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $product = ProductModel::with(['category', 'unit'])
            ->where('id', $id)
            ->where('company_id', $user->company_id)
            ->first()
            ->withTrashed();

        if (!$product) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => 'Produto não encontrado']
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $product
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        // Aplica withTrashed antes de executar a query
        $product = ProductModel::withTrashed()
            ->with([
                'category' => function ($q) {
                    $q->withTrashed(); // inclui categoria soft deleted
                },
                'unit' => function ($q) {
                    $q->withTrashed(); // inclui unidade soft deleted
                }
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
        ], 200);
    }


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
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $product->update(array_merge($data, [
                'availability' => isset($data['availability']) ? implode(',', $data['availability']) : $product->availability,
            ]));

            return response()->json(['success' => true, 'data' => $product], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'errors' => ['general' => $e->getMessage()]], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProductModel $productModel)
    {
        //
    }


    /**
     * Remove um produto (soft delete recomendado).
     */
    public function destroy($id)
    {
        $user = request()->user();
        $product = ProductModel::where('id', $id)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$product) {
            return response()->json(['success' => false, 'errors' => ['general' => 'Produto não encontrado']], 404);
        }

        try {
            $product->delete();

            return response()->json(['success' => true, 'message' => 'Produto removido com sucesso'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'errors' => ['general' => $e->getMessage()]], 500);
        }
    }
}
