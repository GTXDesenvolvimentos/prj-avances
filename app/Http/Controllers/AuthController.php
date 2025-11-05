<?php



namespace App\Http\Controllers;

use App\Models\CompanyModel;
use App\Traits\ApiResponser;
use DB;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

/**
 * @OA\Info(
 *     title="Avances  API",
 *     version="1.0.0",
 *     description="API para o sistema Avances",
 *     @OA\Contact(
 *         email="seu-email@dominio.com"
 *     )
 * )
 * 
 * @OA\Server(
 *      url="https://api.avances.com.br/api/",
 *     description="Ambiente de Produção - Avances API"
 * )
 * 
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="João Silva"),
 *     @OA\Property(property="email", type="string", example="joao@email.com"),
 *     @OA\Property(property="company_id", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="Error",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         @OA\AdditionalProperties(type="array", @OA\Items(type="string"))
 *     )
 * )
 * 
 * 
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class AuthController extends Controller
{
    use ApiResponser;
    /**
     * Registro de novo usuário
     * 
     * @OA\Post(
     *     path="/register",
     *     summary="Registrar novo usuário",
     *     tags={"Autenticação"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","password_confirmation"},
     *             @OA\Property(property="name", type="string", example="João Silva"),
     *             @OA\Property(property="email", type="string", format="email", example="joao@email.com"),
     *             @OA\Property(property="password", type="string", format="password", example="senha123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="senha123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Usuário registrado com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="user", ref="#/components/schemas/User"),
     *             @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erro de validação",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Erro no banco de dados",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erro interno do servidor",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function store(Request $request)
    {
        return $this->apiTryCatch(function () use ($request) {
            $data = $request->json()->all();

            // 🔢 Define o próximo company_id
            $lastCompanyId = User::max('company_id');
            $nextCompanyId = ($lastCompanyId ?? 0) + 1;

            // ✅ Validação dos dados
            $validator = Validator::make($data, [
                // Usuário
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email',
                'password' => 'required|string|min:6|confirmed',


            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors()->toArray());
            }

            DB::beginTransaction();

            try {
                // 👤 Cria o usuário
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'company_id' => $nextCompanyId,
                ]);

                // 🏢 Cria a empresa associada
                $companyData = [
                    'id' => $nextCompanyId,
                    'company_name' => 'Aguardando atualização do '.$nextCompanyId,
                    'tax_id' => $nextCompanyId,
                    'phone' => '00 00000-0000',
                    'status' => $data['status'] ?? 'I', // Inativa por padrão
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ];

                $company = CompanyModel::create($companyData);

                // 🔑 Gera o token JWT
                $token = JWTAuth::fromUser($user);

                // ✅ Confirma as alterações
                DB::commit();

                return $this->createdResponse([
                    'user' => $user,
                    'company' => $company,
                    'token' => $token,
                ], 'User and company registered successfully');
            } catch (\Exception $e) {
                // ❌ Se ocorrer erro, desfaz tudo
                DB::rollBack();

                return $this->errorResponse(
                    'Failed to register user and company: ' . $e->getMessage(),
                    'INTERNAL_ERROR',
                    500
                );
            }
        });
    }

    /**
     * Login de usuário
     * 
     * @OA\Post(
     *     path="/login",
     *     summary="Fazer login",
     *     tags={"Autenticação"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="joao@email.com"),
     *             @OA\Property(property="password", type="string", format="password", example="senha123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login realizado com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Credenciais inválidas",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Credenciais inválidas")
     *         )
     *     )
     * )
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
     * Obter dados do usuário autenticado
     * 
     * @OA\Get(
     *     path="/me",
     *     summary="Obter usuário autenticado",
     *     tags={"Autenticação"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dados do usuário obtidos com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="user", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Token inválido ou expirado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Token inválido ou expirado")
     *         )
     *     )
     * )
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
     * Logout do usuário
     * 
     * @OA\Post(
     *     path="/logout",
     *     summary="Fazer logout",
     *     tags={"Autenticação"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout realizado com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Logout realizado com sucesso")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Falha ao fazer logout",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Falha ao fazer logout")
     *         )
     *     )
     * )
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