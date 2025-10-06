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

        $query = ProductModel::with(['category', 'unit'])->where('company_id', $user->company_id);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('product_code', 'LIKE', "%{$search}%")
                    ->orWhere('name', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('unit_id')) {
            $query->where('unit_id', $request->query('unit_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        if ($request->filled('availability')) {
            $availabilities = explode(',', $request->query('availability'));

            $query->where(function ($q) use ($availabilities) {
                foreach ($availabilities as $availability) {
                    $q->orWhereRaw('FIND_IN_SET(?, availability)', [$availability]);
                }
            });
        }

        if ($request->has('is_dynamic_sale_price')) {
            $query->where('is_dynamic_sale_price', (bool) $request->query('is_dynamic_sale_price'));
        }

        if ($request->has('is_dynamic_rental_price')) {
            $query->where('is_dynamic_rental_price', (bool) $request->query('is_dynamic_rental_price'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
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
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $product = ProductModel::create([
                'unit_id' => $data['unit_id'],
                'category_id' => $data['category_id'],
                'company_id' => $user->company_id,
                'product_code' => $data['product_code'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'],
                'availability' => isset($data['availability']) ? implode(',', $data['availability']) : null,
                'average_cost' => $data['average_cost'] ?? 0,
                'sale_price' => $data['sale_price'] ?? 0,
                'rental_price' => $data['rental_price'] ?? 0,
                'is_dynamic_sale_price' => $data['is_dynamic_sale_price'] ?? false,
                'is_dynamic_rental_price' => $data['is_dynamic_rental_price'] ?? false,
            ]);

            return response()->json(['success' => true, 'data' => $product], 201);
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'errors' => ['database' => $e->getMessage()]
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
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
