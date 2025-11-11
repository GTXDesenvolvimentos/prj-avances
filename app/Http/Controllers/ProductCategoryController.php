<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponser;
use App\Models\ProductCategoryModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductCategoryController extends Controller
{
    use ApiResponser;

    /** LISTAGEM DE CATEGORIAS */
    public function index(Request $request)
    {
        return $this->apiTryCatch(function () use ($request) {
            $user = $request->user();
            $limit = (int) $request->query('limit', default: 25);
            $search = trim($request->query('search', ''), '"\'');

            // Consulta base com company_id
            $query = ProductCategoryModel::where('company_id', $user->company_id);

            // Filtro de busca
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('category', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            // Ordenação
            $query->orderBy('id', 'desc');

            // Paginação
            $categories = $query->paginate($limit);

            return $this->paginatedResponse($categories, 'Product categories retrieved successfully');
        });
    }

    /** CRIAÇÃO DE CATEGORIA */
    public function store(Request $request)
    {
        return $this->apiTryCatch(function () use ($request) {
            $user = $request->user();
            $data = $request->all();
            $data['company_id'] = $user->company_id;
            // Validação dos dados
            $validator = Validator::make($data, [
                'category' => 'required|string|min:1|unique:product_categories,category,NULL,NULL,company_id,' . $user->company_id,
                'description' => 'required|string|min:4|max:500',
                'company_id' => 'required|integer',
                //'company_id' => 'required|integer|exists:companies,id',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors()->toArray());
            }

            // Criação da categoria
            $data = [
                'category' => $request->category,
                'description' => $request->description,
                'company_id' => $user->company_id,
                "created_by" => $user->id,
                "updated_by" => $user->id
            ];

            $category = ProductCategoryModel::create($data);

            return $this->createdResponse($category, 'Product category created successfully');
        });
    }

    /** DETALHES DA CATEGORIA */
    public function show(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {
            $user = $request->user();

            $category = ProductCategoryModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$category) {
                return $this->errorResponse(
                    'Product category not found',
                    'NOT_FOUND',
                    404
                );
            }

            return $this->successResponse($category, 'Product category retrieved successfully');
        });
    }

    /** ATUALIZAÇÃO DA CATEGORIA */
    public function update(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {
            $user = $request->user();
            $data = $request->all();
            $data['company_id'] = $user->company_id;

            // Busca a categoria garantindo que pertence à mesma empresa
            $category = ProductCategoryModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$category) {
                return $this->errorResponse(
                    'Product category not found or not accessible for this user.',
                    'NOT_FOUND',
                    404
                );
            }

            // Validação (ignorando o próprio registro no unique)
            $validator = Validator::make($data, [
                'category' => [
                    'sometimes',
                    'required',
                    'string',
                    'min:1',
                    'max:255',
                    Rule::unique('product_categories')->ignore($id)->where('company_id', $user->company_id)
                ],
                'description' => 'sometimes|required|string|min:4|max:500',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors()->toArray());
            }

             // 💾 Atualiza os dados da categoria
            $category->update([
                'category' => $request->category,
                'description' => $request->description,
                'company_id' => $user->company_id,
                "updated_by" => $user->id,
            ]);

            return $this->updatedResponse($category, 'Product category updated successfully');
        });
    }

    /** EXCLUSÃO DA CATEGORIA */
    public function destroy(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {
            $user = $request->user();

            // Busca a categoria filtrando pelo usuário/empresa
            $category = ProductCategoryModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$category) {
                return $this->errorResponse(
                    'Product category not found or not accessible for this user.',
                    'NOT_FOUND',
                    404
                );
            }

            $category->update([
                'deleted_by' => $user->id
            ]);

            // Soft delete
            $category->delete();

            return $this->deletedResponse(
                'Product category deleted successfully',
                ['id' => $category->id, 'deleted_at' => $category->deleted_at]
            );
        });
    }
}