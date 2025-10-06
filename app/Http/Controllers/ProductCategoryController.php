<?php

namespace App\Http\Controllers;

use App\Models\ProductCategoryModel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

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

    public function store(Request $request)
    {
        $data = $request->json()->all();

        $validator = Validator::make($data, [
            'name' => 'required|string|min:1',
            'description' => 'required|string|min:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $companyId = auth()->user()->company_id;

            $category = ProductCategoryModel::create([
                'name' => $data['name'],
                'description' => $data['description'],
                'company_id' => $companyId,

            ]);

            return response()->json([
                'success' => true,
                'data' => $category,
            ], 201);
        } catch (QueryException $e) {
            // Captura erros do banco (por exemplo, violação de unique, not null)
            return response()->json([
                'success' => false,
                'errors' => [
                    'database' => $e->getMessage(),
                ],
            ], status: 400);
        } catch (\Exception $e) {
            // Outros erros inesperados
            return response()->json([
                'success' => false,
                'errors' => [
                    'general' => $e->getMessage(),
                ],
            ], status: 500);
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
