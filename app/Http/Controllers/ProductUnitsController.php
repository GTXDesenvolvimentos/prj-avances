<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponser;
use App\Models\ProductUnitsModel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductUnitsController extends Controller
{
    use ApiResponser;
    public function index(Request $request)
    {
        return $this->apiTryCatch(function () use ($request) {

            $user = $request->user();
            $limit = (int) $request->query('limit', 25);
            $search = trim($request->query('search', ''), '"\'');

            // Consulta base (com relação company se existir)
            $query = ProductUnitsModel::with([
                'company' => function ($q) {
                    $q->withTrashed();
                }
            ])->where('company_id', $user->company_id);

            // Filtro de busca
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

            // Ordenação
            $query->orderBy('created_at', 'desc');

            // Paginação
            $units = $query->paginate($limit);

            // ✅ Retorno padronizado com paginação
            return $this->paginatedResponse($units, 'Product units retrieved successfully');
        });
    }

    /** CRIAÇÃO DE UNIDADE DE MEDIDA */
    public function store(Request $request)
    {
        return $this->apiTryCatch(function () use ($request) {
            // Dados do usuário autenticado
            $user = $request->user();
            $data = $request->all();
            $data['company_id'] = $user->company_id;
            // Validação dos dados
            $validator = Validator::make($data, [
                'symbol' => 'required|string|min:1|unique:product_units,symbol,NULL,NULL,company_id,' . $user->company_id,
                'description' => 'required|string|min:4',
                'company_id' => 'required|integer',
            ]);
            // Falha na validação
            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors()->toArray());
            }
            // Criação da unidade de medida
            $data = [
                'symbol' => $request->symbol,
                'description' => $request->description,
                'company_id' => $user->company_id,
                "created_by" => $user->id,
                "updated_by" => $user->id
            ];
            // Salva no banco
            $unit = ProductUnitsModel::create($data);
            return $this->createdResponse($unit, 'Product unit created successfully');
        });
    }

    /** RETORNO PARA ALTERAÇÃO */
    public function show(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {

            $user = $request->user(); // usuário autenticado

            $unit = ProductUnitsModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$unit) {
                return $this->errorResponse(
                    'Product unit not found',
                    'NOT_FOUND',
                    404
                );
            }

            return $this->successResponse(
                $unit,
                'Product unit retrieved successfully'
            );
        });
    }

    /** ALTERAÇÃO DA UNIDADE DE MEDIDA */
    public function update(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {

            // Usuário autenticado
            $user = $request->user();
            $data = $request->all();
            $data['company_id'] = $user->company_id;

            // Primeiro buscamos garantindo que pertence à mesma empresa
            $unit = ProductUnitsModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$unit) {
                return $this->errorResponse(
                    'Product unit not found or not accessible for this user.',
                    'NOT_FOUND',
                    404
                );
            }

            $validator = Validator::make($data, [
                'symbol' => 'required|string|min:1|unique:product_units,symbol,NULL,NULL,company_id,' . $user->company_id,
                'description' => 'required|string|min:4',
                'company_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors()->toArray());
            }

            // Atualização
            $unit->update([
                'symbol' => $data['symbol'] ?? $unit->symbol,
                'description' => $data['description'] ?? $unit->description,
                'updated_by' => $user->id
            ]);

            return $this->updatedResponse($unit, 'Product unit updated successfully');
        });
    }


    /** EXCLUSÃO DA UNIDADE DE MEDIDA */
    public function destroy(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {

            $user = $request->user();

            // Busca a unidade filtrando pelo usuário/empresa
            $unit = ProductUnitsModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$unit) {
                return $this->errorResponse(
                    'Product unit not found or not accessible for this user.',
                    'NOT_FOUND',
                    404
                );
            }

            // Soft delete
            $unit->delete();

            // Retorno padronizado de delete
            return $this->deletedResponse('Product unit deleted successfully', ['id' => $unit->id, 'deleted_at' => $unit->deleted_at]);
        });
    }


}
