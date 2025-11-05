<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyModel;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Validator;

class CompanyController extends Controller
{
    use ApiResponser;
    /**
     * Lista todas as empresas com paginação.
     */
    public function index(Request $request)
    {
        return $this->apiTryCatch(function () use ($request) {

            $user = $request->user();
            $limit = (int) $request->query('limit', 25);
            $search = trim($request->query('search', ''), '"\'');

            $query = CompanyModel::query()
                ->where('id', $user->company_id); // 🔒 Filtra pela empresa do usuário;

            // 🔍 Filtro de busca
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('alias', 'LIKE', "%{$search}%")
                        ->orWhere('cnpj', 'LIKE', "%{$search}%")
                        ->withTrashed();
                });
            }

            // ⚙️ Filtro de status
            if ($request->filled('status')) {
                $query->where('status', $request->query('status'));
            }

            // 🔽 Ordenação
            $query->orderBy('created_at', 'desc');

            // 📄 Paginação
            $companies = $query->paginate($limit);

            return $this->paginatedResponse($companies, 'Companies retrieved successfully');
        });
    }


    /**
     * Cadastra uma nova empresa.
     */
    public function store(Request $request)
    {
        return $this->apiTryCatch(function () use ($request) {
            $user = $request->user();
            $data = $request->all();
            $data['company_id'] = $user->company_id;

            $validator = Validator::make($data, [
                'company_name' => 'required|string|max:200|unique:companies,company_name',
                'tax_id' => 'nullable|string|size:14|unique:companies,tax_id',
                'phone' => 'nullable|string|max:20',
            ]);


            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors()->toArray());
            }

            $data = [
                'id' => $user->company_id,
                'address_id' => $request->address_id,
                'company_name' => $request->company_name,
                'tax_id' => $request->tax_id,
                'phone' => $request->phone,
                'status' => 'A', 
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ];


            $unit = CompanyModel::create($data);
            return $this->createdResponse($unit, 'Company created successfully');
        });
    }

    /**
     * Mostra uma empresa específica.
     */
    public function show(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {

            $user = $request->user();

            // 🔍 Busca a empresa pertencente ao mesmo company_id do usuário
            $company = CompanyModel::where('id', $id)
                ->where('id', $user->company_id)
                ->first();


            if (!$company) {
                return $this->errorResponse(
                    'Company not found',
                    'NOT_FOUND',
                    404
                );
            }

            return $this->successResponse(
                $company,
                'Company retrieved successfully'
            );
        });
    }

    public function update(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {

            $user = $request->user();
            $data = $request->all();
            $data['company_id'] = $user->company_id;

            // 🔍 Busca a empresa que o usuário pode acessar
            $company = CompanyModel::where('id', $id)
                ->where('id', $user->company_id)
                ->first();

            if (!$company) {
                return $this->errorResponse(
                    'Company not found or not accessible for this user.',
                    'NOT_FOUND',
                    404
                );
            }

            // ✅ Validação
            $validator = Validator::make($data, [
                'company_name' => 'required|string|max:200|unique:companies,company_name,' . $company->id,
                'tax_id' => 'nullable|string|size:14|unique:companies,tax_id,' . $company->id,
                'phone' => 'nullable|string|max:20',
                //'status' => 'nullable|in:A,I', // A=Ativa, I=Inativa (por exemplo)
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors()->toArray());
            }

            // 💾 Atualiza os dados da empresa
            $company->update([
                'address_id' => $data['address_id'] ?? $company->address_id,
                'company_name' => $data['company_name'] ?? $company->company_name,
                'tax_id' => $data['tax_id'] ?? $company->tax_id,
                'phone' => $data['phone'] ?? $company->phone,
                'status' => "A",
                'updated_by' => $user->id,
            ]);

            // ✅ Retorno padronizado
            return $this->updatedResponse($company, 'Company updated successfully');
        });
    }

    /**
     * Remove uma empresa.
     */
       public function destroy(Request $request, $id)
    {
        return $this->apiTryCatch(function () use ($request, $id) {

            $user = $request->user();

            $unit = CompanyModel::where('id', $id)
                ->where('id', $user->company_id)
                ->first();

            if (!$unit) {
                return $this->errorResponse(
                    'Company not found or not accessible for this user.',
                    'NOT_FOUND',
                    404
                );
            }

            $unit->delete();

            return $this->deletedResponse('Product unit deleted successfully', [
                'id' => $unit->id,
                'deleted_at' => $unit->deleted_at
            ]);
        });
    }
}
