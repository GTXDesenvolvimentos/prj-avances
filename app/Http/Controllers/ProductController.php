<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponser;
use App\Models\ProductModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use ApiResponser;

    /** LISTAGEM DE PRODUTOS */
    public function index(Request $request)
    {
        return $this->apiTryCatch(function () use ($request) {
            $user = $request->user();
            $limit = (int) $request->query('limit', 25);
            $search = trim($request->query('search', ''), '"\'');

            // Consulta base com company_id e relacionamentos
            $query = ProductModel::with([
                'category' => function ($q) {
                    $q->withTrashed();
                },
                'unit' => function ($q) {
                    $q->withTrashed();
                }
            ])->where('company_id', $user->company_id);

            // Filtro de busca
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('product_code', 'LIKE', "%{$search}%")
                        ->orWhere('name', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            // Filtros adicionais
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

            // Ordenação
            $query->orderBy('created_at', 'desc');

            // Paginação
            $products = $query->paginate($limit);

            return $this->paginatedResponse($products, 'Products retrieved successfully');
        });
    }

    /** DETALHES DO PRODUTO */
    public function show(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {
            $user = $request->user();

            $product = ProductModel::with([
                'category' => function ($q) {
                    $q->withTrashed();
                },
                'unit' => function ($q) {
                    $q->withTrashed();
                }
            ])
                ->where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$product) {
                return $this->errorResponse(
                    'Product not found',
                    'NOT_FOUND',
                    404
                );
            }

            return $this->successResponse($product, 'Product retrieved successfully');
        });
    }

    /** CRIAÇÃO DE PRODUTO */
    public function store(Request $request)
    {
        return $this->apiTryCatch(function () use ($request) {
            $user = $request->user();
            $data = $request->all();
            $data['company_id'] = $user->company_id;
            $data['created_by'] = $user->id;
            $data['updated_by'] = $user->id;

            // 🔹 Validação
            $validator = Validator::make($data, [
                'unit_id' => [
                    'required',
                    'integer',
                    Rule::exists('product_units', 'id')
                        ->where(fn($q) => $q->where('company_id', $user->company_id)),
                ],
                'category_id' => [
                    'required',
                    'integer',
                    Rule::exists('product_categories', 'id')
                        ->where(fn($q) => $q->where('company_id', $user->company_id)),
                ],
                'product_code' => [
                    'nullable',
                    'string',
                    Rule::unique('products', 'product_code')
                        ->where(fn($q) => $q->where('company_id', $user->company_id)),
                ],
                'description' => 'nullable|string',
                'availability' => 'nullable|array',
                'availability.*' => 'in:sale,rental,internal',
                'average_cost' => 'nullable|numeric|min:0',
                'sale_price' => 'nullable|numeric|min:0',
                'rental_price' => 'nullable|numeric|min:0',
                'is_dynamic_sale_price' => 'boolean',
                'is_dynamic_rental_price' => 'boolean',
                'company_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors()->toArray());
            }

            $availability = $request->availability;

            if (is_array($availability)) {
                $availability = implode(',', $availability);
            }

            // 🔹 Monta os dados do produto
            $productData = [
                'unit_id' => $data['unit_id'],
                'category_id' => $data['category_id'],
                'company_id' => $user->company_id,
                'product_code' => $data['product_code'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                // Salva como JSON (recomendado) ou string separada
                'availability' => $availability,
                'average_cost' => $data['average_cost'] ?? 0,
                'sale_price' => $data['sale_price'] ?? 0,
                'rental_price' => $data['rental_price'] ?? 0,
                'is_dynamic_sale_price' => $data['is_dynamic_sale_price'] ?? false,
                'is_dynamic_rental_price' => $data['is_dynamic_rental_price'] ?? false,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ];

            // 🔹 Criação
            $product = ProductModel::create($productData);

            return $this->createdResponse($product, 'Product created successfully');
        });
    }

    /** ATUALIZAÇÃO DO PRODUTO */
    public function update(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {
            $user = $request->user();
            $data = $request->all();
            $data['company_id'] = $user->company_id;

            // Busca o produto garantindo que pertence à mesma empresa
            $product = ProductModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$product) {
                return $this->errorResponse(
                    'Product not found or not accessible for this user.',
                    'NOT_FOUND',
                    404
                );
            }

            // Validação (ignorando o próprio registro no unique)
            $validator = Validator::make($data, [
                'unit_id' => 'sometimes|required|integer|exists:product_units,id',
                'category_id' => 'sometimes|required|integer|exists:product_categories,id',
                'name' => [
                    'sometimes',
                    'required',
                    'string',
                    'min:2',
                    Rule::unique('products')->ignore($id)->where('company_id', $user->company_id)
                ],
                'product_code' => [
                    'sometimes',
                    'nullable',
                    'string',
                    Rule::unique('products')->ignore($id)->where('company_id', $user->company_id)
                ],
                'description' => 'sometimes|nullable|string',
                'availability' => 'sometimes|nullable|array',
                'availability.*' => 'in:sale,rental,internal',
                'average_cost' => 'sometimes|nullable|numeric|min:0',
                'sale_price' => 'sometimes|nullable|numeric|min:0',
                'rental_price' => 'sometimes|nullable|numeric|min:0',
                'is_dynamic_sale_price' => 'sometimes|boolean',
                'is_dynamic_rental_price' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors()->toArray());
            }

            // Prepara dados para atualização
            $updateData = [
                'unit_id' => $data['unit_id'] ?? $product->unit_id,
                'category_id' => $data['category_id'] ?? $product->category_id,
                'name' => $data['name'] ?? $product->name,
                'product_code' => $data['product_code'] ?? $product->product_code,
                'description' => $data['description'] ?? $product->description,
                'average_cost' => $data['average_cost'] ?? $product->average_cost,
                'sale_price' => $data['sale_price'] ?? $product->sale_price,
                'rental_price' => $data['rental_price'] ?? $product->rental_price,
                'is_dynamic_sale_price' => $data['is_dynamic_sale_price'] ?? $product->is_dynamic_sale_price,
                'is_dynamic_rental_price' => $data['is_dynamic_rental_price'] ?? $product->is_dynamic_rental_price,
                'updated_by' => $user->id
            ];

            // Processa availability se fornecida
            if (isset($data['availability'])) {
                $updateData['availability'] = !empty($data['availability']) ? implode(',', $data['availability']) : null;
            }

            // Atualização
            $product->update($updateData);

            return $this->updatedResponse($product, 'Product updated successfully');
        });
    }

    /** EXCLUSÃO DO PRODUTO */
    public function destroy(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {
            $user = $request->user();

            // Busca o produto filtrando pelo usuário/empresa
            $product = ProductModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$product) {
                return $this->errorResponse(
                    'Product not found or not accessible for this user.',
                    'NOT_FOUND',
                    404
                );
            }

            // Atualiza deleted_by e faz soft delete
            $product->update([
                'deleted_by' => $user->id
            ]);

            $product->delete();

            return $this->deletedResponse(
                'Product deleted successfully',
                [
                    'id' => $product->id,
                    'deleted_at' => $product->deleted_at
                ]
            );
        });
    }
}