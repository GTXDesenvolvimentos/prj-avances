<?php

namespace App\Http\Controllers;

use App\Models\ProductUnitsModel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductUnitsController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $limit = (int) $request->query('limit', 25);
            $search = trim($request->query('search'), '"\'');

            $query = ProductUnitsModel::where('company_id', $user->company_id);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('symbol', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            $units = $query->paginate($limit);

            return response()->json([
                'success' => true,
                'data' => $units->items(),
                'pagination' => [
                    'page' => $units->currentPage(),
                    'limit' => $units->perPage(),
                    'page_count' => $units->lastPage(),
                    'total_count' => $units->total(),
                ],
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
        $data = $request->all();
        $user = $request->user();

        $validator = Validator::make($data, [
            'symbol' => 'required|string|min:1',
            'description' => 'required|string|min:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $unit = ProductUnitsModel::create([
                'symbol' => $data['symbol'],
                'description' => $data['description'],
                'company_id' => $user->company_id,
            ]);

            return response()->json([
                'success' => true,
                'data' => $unit
            ], 201);
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

    public function show($id)
    {
        try {
            $unit = ProductUnitsModel::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $unit
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }


    public function update(Request $request, $id)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'symbol' => 'sometimes|required|string|min:1',
            'description' => 'sometimes|required|string|min:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $unit = ProductUnitsModel::findOrFail($id);

            $unit->update([
                'symbol' => $data['symbol'] ?? $unit->symbol,
                'description' => $data['description'] ?? $unit->description,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Unidade atualizada com sucesso!',
                'data' => $unit
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


    public function destroy($id)
    {
        try {
            $unit = ProductUnitsModel::findOrFail($id);
            $unit->delete();

            return response()->json([
                'success' => true,
                'message' => 'Unidade removida com sucesso!'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }
}
