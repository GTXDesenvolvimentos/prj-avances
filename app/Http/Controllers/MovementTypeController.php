<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponser;
use App\Models\MovementTypeModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MovementTypeController extends Controller
{
    use ApiResponser;

    /** LISTAGEM DE TIPOS DE MOVIMENTO */
    public function index(Request $request)
    {
        return $this->apiTryCatch(function () use ($request) {
            $user = $request->user();
            $limit = (int) $request->query('limit', 25);
            $search = trim($request->query('search', ''), '"\'');
            $type = strtolower($request->query('type', ''));

            // Consulta base com company_id
            $query = MovementTypeModel::where('company_id', $user->company_id)
                ->with(['company' => fn($q) => $q->withTrashed()]);

            // Filtro de busca
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('movement', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            // Filtro por tipo (in / out)
            if (in_array($type, ['in', 'out'])) {
                $query->where('type', $type);
            }

            // Ordenação
            $query->orderBy('id', 'desc');

            // Paginação
            $movementTypes = $query->paginate($limit);

            return $this->paginatedResponse(
                $movementTypes,
                'Movement types retrieved successfully'
            );
        });
    }

    /** CRIAÇÃO DE TIPO DE MOVIMENTO */
    public function store(Request $request)
    {
        return $this->apiTryCatch(function () use ($request) {
            $user = $request->user();
            $data = $request->all();
            $data['company_id'] = $user->company_id;

            // Validação dos dados
            $validator = Validator::make($data, [
                'movement' => 'required|string|min:1|max:255|unique:movement_type,movement,NULL,NULL,company_id,' . $user->company_id,
                'description' => 'nullable|string|min:3|max:500',
                'type' => [
                    'required',
                    'string',
                    Rule::in(['in', 'out'])
                ],
                'company_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors()->toArray());
            }

            $validated = $validator->validated();

            // Criação do tipo de movimento
            $movementType = MovementTypeModel::create([
                'movement' => $validated['movement'],
                'description' => $validated['description'] ?? null,
                'type' => $validated['type'],
                'company_id' => $user->company_id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            return $this->createdResponse($movementType, 'Movement type created successfully');
        });
    }

    /** DETALHES DE UM TIPO DE MOVIMENTO */
    public function show(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {
            $user = $request->user();

            $movementType = MovementTypeModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->with('company')
                ->first();

            if (!$movementType) {
                return $this->errorResponse(
                    'Movement type not found',
                    'NOT_FOUND',
                    404
                );
            }

            return $this->successResponse($movementType, 'Movement type retrieved successfully');
        });
    }

    /** ATUALIZAÇÃO DE UM TIPO DE MOVIMENTO */
    public function update(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {

            // Usuário autenticado
            $user = $request->user();
            $data = $request->all();
            $data['company_id'] = $user->company_id;

            // Primeiro buscamos garantindo que pertence à mesma empresa
            $unit = MovementTypeModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$unit) {
                return $this->errorResponse(
                    'Movement type not found or not accessible for this user.',
                    'NOT_FOUND',
                    404
                );
            }

            // Validação dos dados
            $validator = Validator::make($data, [
                'movement' => [
                    'required',
                    'string',
                    'min:1',
                    Rule::unique('movement_type', 'movement')
                        ->ignore($id)
                        ->where(fn($q) => $q->where('company_id', $user->company_id)),
                ],
                'description' => 'nullable|string|min:3|max:500',
                'type' => [
                    'required',
                    'string',
                    Rule::in(['in', 'out'])
                ],
                'company_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors()->toArray());
            }

            // Atualização dos dados
            $unit->update([
                'movement' => $data['movement'] ?? $unit->movement,
                'description' => $data['description'] ?? $unit->description,
                'type' => $data['type'] ?? $unit->type,
                'updated_by' => $user->id
            ]);

            return $this->updatedResponse($unit, 'Movement type updated successfully');
        });
    }

    /** EXCLUSÃO (SOFT DELETE) DE UM TIPO DE MOVIMENTO */
    public function destroy(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {
            $user = $request->user();

            // Busca o tipo de movimento filtrando pelo usuário/empresa
            $movementType = MovementTypeModel::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (!$movementType) {
                return $this->errorResponse(
                    'Movement type not found or not accessible for this user.',
                    'NOT_FOUND',
                    404
                );
            }

            // Atualiza o campo deleted_by antes do soft delete
            $movementType->update([
                'deleted_by' => $user->id
            ]);

            // Soft delete
            $movementType->delete();

            return $this->deletedResponse(
                'Movement type deleted successfully',
                ['id' => $movementType->id, 'deleted_at' => $movementType->deleted_at]
            );
        });
    }
}
