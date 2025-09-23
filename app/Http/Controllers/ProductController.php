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
        public function create(Request $request)   
        {
            $data = $request->json()->all();

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed', // password_confirmation obrigatório
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'success' => true,
            'user' => $user,
            'token' => $token
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
