<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\ProductModel;
use Illuminate\Support\Facades\Validator;




class ProductController extends Controller
{


    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = ProductModel::getAllProducts();
        return response()->json($products);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {



        $data = $request->json()->all();
        echo json_encode($data);

        $validator = Validator::make($data, [
            'product_code' => 'required|string|min:5',
            'description' => 'required|string|min:6',
            'category_id' => 'required|string|min:1',
            'unit_id' => 'required|string|min:1',
            'cost_price' => 'required|string|min:1',
            'sale_price' => 'required|string|min:1',
        ]);
        try {
            $product = ProductModel::create([



                'average_cost'=> $data['average_cost'],
                'rental_price'  => $data['rental_price'],
                'is_dynamic_sale_price' => $data['is_dynamic_sale_price'],
                'is_dynamic_rental_price'   => $data['is_dynamic_rental_price'],
                'product_code' => $data['product_code'],
                'description' => $data['description'],
                'category_id' => $data['category_id'],
                'supplier_id' => $data['supplier_id'],
                'unit_id' => $data['unit_id'],
                'cost_price' => $data['cost_price'],
                'sale_price' => $data['sale_price'],
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

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(ProductModel $productModel)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProductModel $productModel)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProductModel $productModel)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductModel $productModel)
    {
        //
    }



}
