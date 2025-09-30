<?php

namespace App\Http\Controllers;

use App\Models\ProductUnitsModel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductUnitsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->json()->all();
        echo json_encode($data);

        $validator = Validator::make($data, [
            'symbol' => 'required|string|min:1',
            'description' => 'required|string|min:6',
        ]);
        try {
            $units = ProductUnitsModel::create([
                'symbol' => $data['symbol'],
                'description' => $data['description'],
                'company_id' => $data['company_id'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $units,
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

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        try {
            $units = ProductUnitsModel::all();

            return response()->json([
                'success' => true,
                'data' => $units,
                'count' => $units->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => ['general' => $e->getMessage()]
            ], 500);
        }
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProductUnits $productUnits)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductUnits $productUnits)
    {
        //
    }
}
