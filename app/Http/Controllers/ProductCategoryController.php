<?php

namespace App\Http\Controllers;

use App\Models\ProductCategoryModel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProductCategoryController extends Controller
{

    public function index()
    {
        //
    }

    public function show(Request $request)
    {
        try {
            $productCategories = ProductCategoryModel::all();

            return response()->json([
                'success' => true,
                'data' => $productCategories,
                'count' => $productCategories->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $company_id = JWTAuth::parseToken()->authenticate()->company_id;
        $data = $request->json()->all();
        echo json_encode($data);

        $validator = Validator::make($data, [
            'name' => 'required|string|min:1',
            'description' => 'required|string|min:6',
        ]);
        try {
            $product = ProductCategoryModel::create([
                'name' => $data['name'],
                'description' => $data['description'],
                'company_id' => $company_id,
            ]);

            return response()->json([
                'success' => true,
                'data' => $product,
            ], 201);

        } catch (QueryException $e) {
            // Captura erros do banco (por exemplo, violação de unique, not null)
            return response()->json([
                'success' => false,
                'errors' => [
                    'database' => $e->getMessage()
                ]
            ], status: 400);
        } catch (\Exception $e) {
            // Outros erros inesperados
            return response()->json([
                'success' => false,
                'errors' => [
                    'general' => $e->getMessage()
                ]
            ], status: 500);
        }




        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
            
    }
        

    public function update(Request $request)
    {
        $data = $request->json()->all();

        // Validação dos dados
        $validator = Validator::make($data, [
            'name' => 'sometimes|required|string|min:1',
            'description' => 'sometimes|required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $productCategory = ProductCategoryModel::findOrFail(id: $data['id']);

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
                'errors' => ['database' => $e->getMessage()]
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }

    public function destroy(Request $request)
    {
        $data = $request->json()->all();

        // Validação do ID
        $validator = Validator::make($data, [
            'id' => 'required|integer|exists:product_categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $productCategory = ProductCategoryModel::findOrFail($data['id']);
            $productCategory->delete();

            return response()->json([
                'success' => true,
                'message' => 'Category successfully deleted!',
                'data' => [
                    'id' => $data['id'],
                    'deleted_at' => now()
                ]
            ], 200);

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
}
