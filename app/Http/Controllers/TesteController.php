<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    /**
     * Cadastro de usuário
     */
    public function register(Request $request)
    {
        // Garantir que Laravel interprete o JSON
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
     * Login de usuário
     */
    public function login(Request $request)
    {
        $credentials = $request->json()->all();
        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciais inválidas'
            ], 401);
        }
    
        return response()->json([
            'success' => true,
            'token' => $token
        ]);
    }

    /**
     * Usuário autenticado
     */
    public function me(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido ou expirado'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'user' => $user
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        try {
            JWTAuth::parseToken()->invalidate();
            return response()->json([
                'success' => true,
                'message' => 'Logout realizado com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Falha ao fazer logout'
            ], 500);
        }
    }
}
