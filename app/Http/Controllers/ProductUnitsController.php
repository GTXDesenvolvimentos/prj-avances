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
            $search = trim($request->query('search', ''), '"\'');

            // Consulta base (carrega também company se existir relação)
            $query = ProductUnitsModel::with([
                'company' => function ($q) {
                    $q->withTrashed();
                }
            ])->where('company_id', $user->company_id);

            // Filtro de busca (por símbolo ou descrição)
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('symbol', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%")
                        ->withTrashed();
                });
            }

            // Filtro opcional por status
            if ($request->filled('status')) {
                $query->where('status', $request->query('status'));
            }

            // Ordenação (mais recentes primeiro)
            $query->orderBy('created_at', 'desc');

            // Paginação
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
                'message' => 'Error while listing product units.',
                'error' => $e->getMessage(),
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
            // Busca a unidade (lança 404 se não existir)
            $unit = ProductUnitsModel::findOrFail($id);

            // Soft delete
            $unit->delete();

            return response()->json([
                'success' => true,
                'message' => 'Unidade marcada como excluída com sucesso!',
                'data' => [
                    'id' => $unit->id,
                    'deleted_at' => $unit->deleted_at,
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => 'Unidade não encontrada.'],
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => $e->getMessage()],
            ], 500);
        }
    }

}
