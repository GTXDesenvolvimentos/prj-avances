<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
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

        $lastCompanyId = User::max('company_id');

        // Adiciona 1 (começando de 1 se estiver vazio)
        $nextCompanyId = ($lastCompanyId ?? 0) + 1;


        // Validação inicial
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Tentar criar o usuário
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'company_id' => $nextCompanyId,
            ]);

            // Gerar token JWT
            $token = JWTAuth::fromUser($user);

            return response()->json([
                'success' => true,
                'user' => $user,
                'token' => $token
            ], status: 200);

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
