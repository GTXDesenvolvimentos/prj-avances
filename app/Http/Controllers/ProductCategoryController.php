<?php

namespace App\Http\Controllers;

use App\Models\ProductCategoryModel;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ProductCategoryController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $limit = (int) $request->query('limit', 15);
            $search = trim($request->query('search'), '"\'');


            $query = ProductCategoryModel::where('company_id', $user->company_id);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            // 🔹 Ordenar pelo mais recente primeiro
            $query->orderBy('created_at', 'desc');

           

            $categories = $query->paginate($limit);

            return response()->json([
                'success' => true,
                'data' => $categories->items(),
                'pagination' => [
                    'page' => $categories->currentPage(),
                    'limit' => $categories->perPage(),
                    'page_count' => $categories->lastPage(),
                    'total_count' => $categories->total(),
                ],
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'errors' => [
                    'database' => $e->getMessage(),
                ],
            ], status: 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => [
                    'general' => $e->getMessage(),
                ],
            ], status: 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1️⃣ Verifica se o usuário está vinculado a uma empresa
        if (!$user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário não está vinculado a nenhuma empresa.',
            ], 403);
        }

        try {
            DB::beginTransaction();

            // 2️⃣ Injeta company_id do usuário autenticado
            $request->merge(['company_id' => $user->company_id]);

            // 3️⃣ Validação dos dados
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|min:1|max:255',
                'description' => 'required|string|min:4|max:500',
                'company_id' => 'required|integer|exists:companies,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();

            // 4️⃣ Criação da categoria
            $category = ProductCategoryModel::create($validated);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product category created successfully.',
                'data' => $category,
            ], 201);

        } catch (QueryException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Database error.',
                'error' => $e->getMessage(),
            ], 400);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error creating product category.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'name' => 'sometimes|required|string|min:1',
            'description' => 'sometimes|required|string|min:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $productCategory = ProductCategoryModel::findOrFail($id);

            $productCategory->update([
                'name' => $data['name'] ?? $productCategory->name,
                'description' => $data['description'] ?? $productCategory->description,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Categoria atualizada com sucesso!',
                'data' => $productCategory,
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'errors' => ['database' => $e->getMessage()],
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => $e->getMessage()],
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            // Busca a categoria (lança 404 se não existir)
            $category = ProductCategoryModel::findOrFail($id);

            // Soft delete
            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Categoria marcada como excluída com sucesso!',
                'data' => [
                    'id' => $category->id,
                    'deleted_at' => $category->deleted_at, // data do soft delete
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => 'Categoria não encontrada.'],
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => $e->getMessage()],
            ], 500);
        }
    }



    // public function destroy($id)
    // {
    //     try {
    //         $category = ProductCategoryModel::findOrFail($id);
    //         $category->delete();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Categoria removida com sucesso!',
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'errors' => ['general' => $e->getMessage()],
    //         ], 500);
    //     }
    // }
}