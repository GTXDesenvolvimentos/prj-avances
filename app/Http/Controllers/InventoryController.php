<?php

namespace App\Http\Controllers;

use App\Models\ProductModel;
use App\Models\InventoryModel;
use App\Models\MovementTypeModel;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     * @param  Request  $request
     */

use ApiResponser;

 public function index(Request $request)
{

    return $this->apiTryCatch (function () use ($request) {
        // 🔹 Usuário autenticado
        $user = $request->user();
        $companyId = $user->company_id;

        // 🔹 Parâmetros de filtro e paginação
        $limit = (int) $request->query('limit', 25);
        $page = (int) $request->query('page', 1);
        $search = trim($request->query('search', ''), "\"'");
        $productId = $request->query('product_id');
        $quantityBelow = $request->query('quantity_below');

        // 🔹 Query base com relacionamentos
        $query = InventoryModel::with([
            'category',
            'unit',
            'warehouse',
            'movement_type', // para saber se é entrada ou saída
        ])->where('company_id', $companyId);

        // 🔹 Filtros opcionais
        if (!empty($productId)) {
            $query->where('product_id', $productId);
        }

        if (!empty($search)) {
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($quantityBelow)) {
            $query->where('quantity', '<', $quantityBelow);
        }

        // 🔹 Ordenação
        $query->orderBy('created_at', 'desc');

        // 🔹 Paginação
        $inventories = $query->paginate($limit, ['*'], 'page', $page);

        // 🔹 Retorno padronizado
        return $this->paginatedResponse($inventories,'Inventory records retrieved successfully');
    });
}





}