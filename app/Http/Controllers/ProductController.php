<?php

namespace App\Http\Controllers;

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
            //
        }
    
        /**
         * Show the form for creating a new resource.
         */
        public function createProduct(Request $request)   
        {
            $data = $request->json()->all();
            echo json_encode($data);

        $validator = Validator::make($data, [
            'product_code' => 'required|string|min:5', 
            'description'  => 'required|string|min:6',
            'category_id'  => 'required|string|min:1',
            'supplier_id'  => 'required|string|min:1',
            'unit_id'  => 'required|string|min:1',
            'cost_price'  => 'required|string|min:1',
            'sale_price'  => 'required|string|min:1',
            //'name' => 'required|string|max:255',
            //'email' => 'required|string|email|max:255|unique:users',
           // 'password' => 'required|string|min:6|confirmed', // password_confirmation obrigatório
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $product = ProductModel::create([
            'product_code' => $data['product_code'],
            'description'  => $data['description'],
            'category_id'  => $data['category_id'],
            'supplier_id'  => $data['supplier_id'],
            'unit_id'  => $data['unit_id'],
            'cost_price'  => $data['cost_price'],
            'sale_price'  => $data['sale_price'],
        ]);
        
        return response()->json([
            'success' => true,
            'user' => 'user',
            'token' => 'token'
        ], 201);
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
