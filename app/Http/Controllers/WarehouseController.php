<?php

namespace App\Http\Controllers;
use App\Models\WarehouseModel;
use App\Traits\ApiResponser;
use Attribute;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WarehouseController extends Controller
{
    /**
     * List all warehouses (index)
     */

    use ApiResponser;

    /** LISTAGEM DE DEPÓSITOS */
    public function index(Request $request)
    {
        return $this->apiTryCatch(function () use ($request) {

            $user = $request->user();
            $limit = (int) $request->query('limit', 25);
            $search = trim($request->query('search', ''), '"\'');

            // 🔒 Consulta base — apenas registros da empresa do usuário
            $query = WarehouseModel::query()
                ->where('company_id', $user->company_id);

            // 🔍 Filtro de busca
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('warehouse', 'LIKE', "%{$search}%")
                        ->orWhere('note', 'LIKE', "%{$search}%");
                });
            }

            // ⚙️ Filtro de status (opcional)
            if ($request->filled('status')) {
                $query->where('status', $request->query('status'));
            }

            // 🔽 Ordenação
            $query->orderBy('created_at', 'desc');

            // 📄 Paginação
            $warehouses = $query->paginate($limit);

            // 🧠 Debug opcional: descomente se quiser inspecionar a query
            // dd($request->user())['company_id'];

            return $this->paginatedResponse($warehouses, 'Warehouses retrieved successfully');
        });
    }




    /** CRIAÇÃO DE DEPÓSITOS */
    public function store(Request $request)
    {
        return $this->apiTryCatch(function () use ($request) {
            $user = $request->user();
            $data = $request->all();
            $data['company_id'] = $user->company_id;

            // Validação dos dados
            $validator = Validator::make($data, [
                'address_id' => 'required|integer',
                'warehouse' => 'required|string|min:1|unique:warehouses,warehouse,NULL,NULL,company_id,' . $user->company_id,
                'note' => 'nullable|string',
                'company_id' => 'required|integer',
                //'company_id' => 'required|integer|exists:companies,id',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors()->toArray());
            }

            // Criação da categoria
            $data = [
                'address_id' => $request->address_id,
                'warehouse' => $request->warehouse,
                'note' => $request->note,
                'company_id' => $user->company_id,
                "created_by" => $user->id,
                "updated_by" => $user->id
            ];

            $warehouse = WarehouseModel::create($data);

            return $this->createdResponse($warehouse, 'Warehouse created successfully');
        });

    }

    public function show(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {
            $user = $request->user();

            $warehouse = WarehouseModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$warehouse) {
                return $this->errorResponse(
                    'Warehouse not found',
                    'NOT_FOUND',
                    404
                );
            }

            return $this->successResponse($warehouse, 'Warehouse retrieved successfully');
        });
    }

    /** ATUALIZAÇÃO DA WAREHOUSE */
    public function update(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {
            $user = $request->user();
            $data = $request->all();
            $data['company_id'] = $user->company_id;

            // Busca a wharehouse garantindo que pertence à mesma empresa
            $warehouse = WarehouseModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$warehouse) {
                return $this->errorResponse(
                    'Warehouse not found or not accessible for this user.',
                    'NOT_FOUND',
                    404
                );
            }

            // Validação (ignorando o próprio registro no unique)
            $validator = Validator::make($data, [
                'address_id' => 'required|integer',
                'warehouse' => 'required|string|min:1|unique:warehouses,warehouse,' . $id . ',id,company_id,' . $user->company_id,
                'note' => 'nullable|string',
                'company_id' => 'required|integer',
                //'company_id' => 'required|integer|exists:companies,id',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors()->toArray());
            }



            // Atualização
            // Atualização correta
            $warehouse->update([
                'address_id' => $request->address_id ?? $warehouse->address_id,
                'warehouse' => $request->warehouse ?? $warehouse->warehouse,
                'note' => $request->note ?? $warehouse->note,
                'updated_by' => $user->id,
                'company_id' => $user->company_id,
            ]);

            return $this->updatedResponse($warehouse, 'Warehouse updated successfully');
        });
    }


    /**
     * Update an existing warehouse
     */
    public function update1(Request $request, $id)
    {
        try {
            $warehouse = WarehouseModel::find($id);

            if (!$warehouse) {
                return response()->json([
                    'success' => false,
                    'message' => 'Warehouse not found.'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'address_id' => 'sometimes|required|integer',
                'name' => 'sometimes|required|string|max:255',
                'note' => 'nullable|string',
                'status' => 'sometimes|required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $warehouse->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Warehouse successfully updated!',
                'data' => $warehouse
            ], 200);

        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database error while updating warehouse.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Soft delete a warehouse
     */
    public function destroy($id)
    {
        try {
            $warehouse = WarehouseModel::find($id);

            if (!$warehouse) {
                return response()->json([
                    'success' => false,
                    'message' => 'Warehouse not found.'
                ], 404);
            }

            $warehouse->delete();

            return response()->json([
                'success' => true,
                'message' => 'Warehouse successfully deleted.'
            ], 200);

        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error while deleting warehouse.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
