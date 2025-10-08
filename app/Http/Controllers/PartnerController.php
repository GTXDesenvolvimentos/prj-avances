<?php

namespace App\Http\Controllers;

use App\Models\PartnerModel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PartnerController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $limit = (int) $request->query('limit', 15);
            $search = trim($request->query('search', ''), '"\'');
            $partner_type = $request->query('partner_type');
            $status = $request->query('status');

            $query = PartnerModel::where('company_id', $user->company_id);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('tax_id', 'LIKE', "%{$search}%")
                        ->orWhere('note', 'LIKE', "%{$search}%");
                });
            }

            if ($partner_type) {
                $query->where('partner_type', $partner_type);
            }

            if ($status !== null) {
                $query->where('status', filter_var($status, FILTER_VALIDATE_BOOLEAN));
            }

            $partners = $query->paginate($limit);

            return response()->json([
                'success' => true,
                'data' => $partners->items(),
                'pagination' => [
                    'page' => $partners->currentPage(),
                    'limit' => $partners->perPage(),
                    'page_count' => $partners->lastPage(),
                    'total_count' => $partners->total(),
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
            'name' => 'required|string|min:1|max:255',
            'tax_id' => 'required|string|max:20|unique:partners,tax_id',
            'partner_type' => ['required', 'string', Rule::in(['customer', 'supplier', 'distributor', 'reseller', 'partner'])],
            'status' => 'boolean',
            'note' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $companyId = auth()->user()->company_id;

            $partner = PartnerModel::create([
                'name' => $data['name'],
                'tax_id' => $data['tax_id'],
                'partner_type' => $data['partner_type'],
                'company_id' => $companyId,
                'status' => $data['status'] ?? true,
                'note' => $data['note'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'data' => $partner,
            ], 201);
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

    public function show($id)
    {
        try {
            $user = auth()->user();
            $partner = PartnerModel::where('company_id', $user->company_id)
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $partner,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => 'Parceiro não encontrado.'],
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => $e->getMessage()],
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'name' => 'sometimes|required|string|min:1|max:255',
            'tax_id' => 'sometimes|required|string|max:20|unique:partners,tax_id,' . $id,
            'partner_type' => ['sometimes', 'required', 'string', Rule::in(['customer', 'supplier', 'distributor', 'reseller', 'partner'])],
            'status' => 'boolean',
            'note' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = auth()->user();
            $partner = PartnerModel::where('company_id', $user->company_id)
                ->findOrFail($id);

            $partner->update([
                'name' => $data['name'] ?? $partner->name,
                'tax_id' => $data['tax_id'] ?? $partner->tax_id,
                'partner_type' => $data['partner_type'] ?? $partner->partner_type,
                'status' => $data['status'] ?? $partner->status,
                'note' => $data['note'] ?? $partner->note,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Parceiro atualizado com sucesso!',
                'data' => $partner,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => 'Parceiro não encontrado.'],
            ], 404);
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
            $user = auth()->user();
            $partner = PartnerModel::where('company_id', $user->company_id)
                ->findOrFail($id);

            $partner->delete();

            return response()->json([
                'success' => true,
                'message' => 'Parceiro marcado como excluído com sucesso!',
                'data' => [
                    'id' => $partner->id,
                    'deleted_at' => $partner->deleted_at,
                ],
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => 'Parceiro não encontrado.'],
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => $e->getMessage()],
            ], 500);
        }
    }

    public function restore($id)
    {
        try {
            $user = auth()->user();
            $partner = PartnerModel::withTrashed()
                ->where('company_id', $user->company_id)
                ->findOrFail($id);

            if (!$partner->trashed()) {
                return response()->json([
                    'success' => false,
                    'errors' => ['general' => 'Parceiro não está excluído.'],
                ], 400);
            }

            $partner->restore();

            return response()->json([
                'success' => true,
                'message' => 'Parceiro restaurado com sucesso!',
                'data' => $partner,
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => 'Parceiro não encontrado.'],
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => $e->getMessage()],
            ], 500);
        }
    }

    public function forceDelete($id)
    {
        try {
            $user = auth()->user();
            $partner = PartnerModel::withTrashed()
                ->where('company_id', $user->company_id)
                ->findOrFail($id);

            $partner->forceDelete();

            return response()->json([
                'success' => true,
                'message' => 'Parceiro excluído permanentemente com sucesso!',
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => 'Parceiro não encontrado.'],
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => $e->getMessage()],
            ], 500);
        }
    }

    public function activePartners()
    {
        try {
            $user = auth()->user();
            $partners = PartnerModel::where('company_id', $user->company_id)
                ->active()
                ->get();

            return response()->json([
                'success' => true,
                'data' => $partners,
                'count' => $partners->count(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => $e->getMessage()],
            ], 500);
        }
    }

    public function byType(Request $request, $type)
    {
        try {
            $validTypes = ['customer', 'supplier', 'distributor', 'reseller', 'partner'];

            if (!in_array($type, $validTypes)) {
                return response()->json([
                    'success' => false,
                    'errors' => ['general' => 'Tipo de parceiro inválido.'],
                ], 400);
            }

            $user = auth()->user();
            $partners = PartnerModel::where('company_id', $user->company_id)
                ->byType($type)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $partners,
                'count' => $partners->count(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => $e->getMessage()],
            ], 500);
        }
    }
}