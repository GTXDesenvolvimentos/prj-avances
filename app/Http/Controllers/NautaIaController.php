<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * NautaIaController
 *
 * Refatoração do controller original com:
 * - Execução de até 6 queries geradas pela IA (tolerância a falhas em cada query)
 * - Middleware/guard para validar queries contra whitelist e proteger contra injection
 * - Integração opcional com serviço de busca externa (ex: SerpAPI) para "dados de mercado"
 * - Mesclagem das respostas internas + externas via IA menor (synth)
 * - Modo debug que retorna prompts e respostas brutas quando ?debug=1
 *
 * Obs: coloque as classes/middlewars em arquivos separados conforme preferir. Aqui estão
 * juntas para facilitar o copy-paste e testes.
 */
class NautaIaController extends Controller
{
    // Tabelas permitidas (whitelist)
    protected array $tablesToAnalyze = [
        'contact_entities',
        'inventory_movements',
        'movement_type',
        'partners',
        'product_categories',
        'products',
        'product_units',
        'warehouses'
    ];

    // Número máximo de queries que a IA pode retornar/que iremos executar
    protected int $maxQueries = 6;

    // Modelos usados
    protected string $decisionModel = 'meta-llama/Llama-3.1-70B-Instruct';
    protected string $synthModel = 'meta-llama/Llama-3.1-8B-Instruct';

    // Timeouts HTTP
    protected int $huggingfaceTimeout = 30; // segundos

    /**
     * Retorna metadados formatados das tabelas solicitadas (igual ao original)
     */
    public function getFormattedTableColumns(array $tablesToInclude): string
    {
        $databaseName = config('database.connections.' . config('database.default') . '.database');
        $tableList = "'" . implode("', '", $tablesToInclude) . "'";

        $sql = "
        SELECT
            TABLE_NAME,
            COLUMN_NAME,
            DATA_TYPE,
            COLUMN_TYPE,
            IS_NULLABLE,
            COLUMN_KEY,
            COLUMN_COMMENT
        FROM
            INFORMATION_SCHEMA.COLUMNS
        WHERE
            TABLE_SCHEMA = ?
            AND TABLE_NAME IN ({$tableList})
        ORDER BY
            TABLE_NAME, ORDINAL_POSITION;
        ";

        $columns = DB::select($sql, [$databaseName]);

        $columnsText = array_map(function ($c) {
            $nullable = ($c->IS_NULLABLE === 'YES') ? 'YES' : 'NO';
            $comment = !empty($c->COLUMN_COMMENT) ? " // Objetivo: {$c->COLUMN_COMMENT}" : '';
            $typeDetail = (str_contains($c->COLUMN_TYPE, 'enum') || str_contains($c->COLUMN_TYPE, 'set'))
                ? " ({$c->COLUMN_TYPE})"
                : '';

            return "Tabela: {$c->TABLE_NAME}, Coluna: {$c->COLUMN_NAME}, Tipo: {$c->DATA_TYPE}{$typeDetail}, Nulo: {$nullable}{$comment}";
        }, $columns);

        return implode("\n", $columnsText);
    }

    /**
     * Endpoint principal. Recebe `question` e retorna JSON com execução de queries
     */
    public function chat(Request $request)
    {
        $userQuestion = $request->input('question');
        $companyId = $request->user()->company_id ?? null;
        $debug = $request->boolean('debug');

        if (!$companyId) {
            return response()->json(['error' => 'company_id não encontrado no usuário autenticado.'], 400);
        }

        // 1. Metadados
        $columns_text = $this->getFormattedTableColumns($this->tablesToAnalyze);

        // 2. Gera prompt para decisão (até 6 queries, output JSON estruturado)
        $decisionPrompt = $this->buildDecisionPrompt($userQuestion, $companyId, $columns_text);

        // Chama IA de decisão
        try {
            $decisionResponse = Http::timeout($this->huggingfaceTimeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . env('HUGGINGFACE_API_KEY'),
                    'Content-Type' => 'application/json',
                ])->post('https://router.huggingface.co/v1/chat/completions', [
                    'model' => $this->decisionModel,
                    'messages' => [
                        ['role' => 'user', 'content' => $decisionPrompt]
                    ]
                ]);
        } catch (Exception $e) {
            Log::error('HF decision call failed: ' . $e->getMessage());
            return response()->json(['error' => 'Falha ao contatar o serviço de IA (decisão).'], 500);
        }

        $aiOutput = $decisionResponse->json()['choices'][0]['message']['content'] ?? '';
        $aiData = json_decode($aiOutput, true);
        if (!$aiData && preg_match('/\{.*\}/s', $aiOutput, $matches)) {
            $aiData = json_decode($matches[0], true);
        }

        if (!$aiData) {
            Log::error('AI decision JSON could not be decoded', ['raw' => $aiOutput]);
            return response()->json(['error' => 'Não foi possível decodificar a resposta da IA (decisão).', 'raw' => $aiOutput], 500);
        }

        // Normalize queries array
        $queriesFromAi = [];
        if (!empty($aiData['queries']) && is_array($aiData['queries'])) {
            $queriesFromAi = array_slice($aiData['queries'], 0, $this->maxQueries);
        } elseif (!empty($aiData['query'])) {
            $queriesFromAi = [['query' => $aiData['query']]];
        }

        // Safety: bloquear queries acima do limite
        $queriesFromAi = array_slice($queriesFromAi, 0, $this->maxQueries);

        // Executa queries (até 6), tolerando falhas em cada uma
        $queriesResults = [];

        foreach ($queriesFromAi as $idx => $qObj) {
            $rawQuery = trim($qObj['query'] ?? '');
            if (empty($rawQuery)) {
                $queriesResults[] = [
                    'query' => null,
                    'error' => 'empty_query',
                    'results' => []
                ];
                continue;
            }

            // Normaliza: adiciona filtro company_id se ausente e transforma em binding
            $prepared = $this->prepareQueryWithCompanyId($rawQuery, $companyId);
            if ($prepared['rejected']) {
                $queriesResults[] = [
                    'query' => $rawQuery,
                    'error' => 'rejected_by_guard',
                    'reason' => $prepared['reason'] ?? 'query not allowed',
                    'results' => []
                ];
                continue;
            }

            try {
                // Execute safely with bindings
                $res = DB::select($prepared['sql'], $prepared['bindings']);
                $queriesResults[] = [
                    'query' => $prepared['sql'],
                    'results' => $res,
                    'error' => null
                ];
            } catch (Exception $e) {
                // Log and continue
                Log::error('Query execution failed', ['query' => $prepared['sql'], 'message' => $e->getMessage()]);
                $queriesResults[] = [
                    'query' => $prepared['sql'],
                    'results' => [],
                    'error' => 'execution_error',
                    'message' => $e->getMessage()
                ];
            }
        }

        // Se necessário, buscar dados externos (mídia/mercado)
        $internetResults = '';
        $shouldSearchInternet = ($aiData['source'] ?? 'misto') !== 'banco';

        if ($shouldSearchInternet) {
            try {
                $resp = $this->performMarketSearch($userQuestion, json_encode($queriesResults));

                // Extrair apenas o conteúdo
                $internetResults = $resp['choices'][0]['message']['content'] ?? '';
                error_log('$internetResults: ' . $internetResults);
            } catch (Exception $e) {
                Log::warning('Market search failed: ' . $e->getMessage());
                $internetResults = '';
            }
        }

        // Agora: sintetizar (mesclar) resultados com a IA menor
        $synthPrompt = $this->buildSynthPrompt($userQuestion, $queriesResults, $internetResults);

        try {
            $synthResponse = Http::timeout($this->huggingfaceTimeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . env('HUGGINGFACE_API_KEY'),
                    'Content-Type' => 'application/json',
                ])->post('https://router.huggingface.co/v1/chat/completions', [
                    'model' => $this->synthModel,
                    'messages' => [
                        ['role' => 'user', 'content' => $synthPrompt]
                    ]
                ]);
        } catch (Exception $e) {
            Log::error('HF synth call failed: ' . $e->getMessage());
            return response()->json(['error' => 'Falha ao contatar o serviço de IA (synth).'], 500);
        }

        $finalAnswer = $synthResponse->json()['choices'][0]['message']['content'] ?? '';

        $payload = [
            'needQuery' => count($queriesFromAi) > 0,
            'queries' => array_map(fn($q) => $q['query'] ?? null, $queriesFromAi),
            'queriesResults' => $queriesResults,
            'internetResults' => $internetResults,
            'source' => $aiData['source'] ?? 'misto',
            'answer' => trim($finalAnswer)
        ];

        if ($debug) {
            $payload['debug'] = [
                'decision_prompt' => $decisionPrompt,
                'decision_raw' => $aiOutput,
                'decision_json' => $aiData,
                'synth_prompt' => $synthPrompt,
                'synth_raw' => $synthResponse->body()
            ];
        }

        return response()->json($payload);
    }

    /**
     * Constrói o prompt de decisão enviado ao modelo grande.
     * Pede explicitamente que retorne JSON com até 6 queries.
     */
    protected function buildDecisionPrompt(string $userQuestion, $companyId, string $columnsText): string
    {
        $tablesCsv = implode(', ', $this->tablesToAnalyze);

        return <<<PROMPT
        Você é um Assistente de Análise (NautaIA) integrado ao sistema Avances.
        Com base estritamente nos metadados do banco e na pergunta do usuário, decida se são necessárias queries SQL (até 6) para responder.
        Retorne APENAS um objeto JSON válido seguindo o formato abaixo.

        Regras IMPORTANTES:
        - Não invente nomes de tabelas/colunas: use somente as colunas e tabelas fornecidas nos Metadados.
        - Máximo de 6 queries.
        - Todas as queries devem ser somente leitura (SELECT).
        - Não use palavras de escrita/alteração (DELETE, UPDATE, INSERT, DROP, ALTER).
        - As queries podem (e devem) incluir o filtro company_id = $companyId. Se faltar, o backend irá adicionar.
        - Evite joins complexos. Prefira selecionar colunas específicas ou SELECT * de tabelas da whitelist.
        - Se os dados internos forem insuficientes para responder, indique source: "misto".
        - Tente no mínimo criar 2 queries para entender o contexto da empresa para quando for pesquisar na internet
        - Uma das queries sempre deve ser na tabela de produto para que voce entenda o ramo da empresa
        - Se a pergunta for sobre estoque a tabela que é possível saber o estoque é a inventory_movements. Ela não trás o estoque atual mas no linha mais recente de cada produto e cada warehouse tem a quantidade total

        Formato de saída JSON (exato):
        {
        "needQuery": true|false,
        "source": "banco"  | "misto",
        "queries": [
            { "query": "SELECT ... FROM ... WHERE ... LIMIT 100" },
            ... até 6 itens
        ],
        "answer": "<se desejar sugerir uma resposta curta (opcional)>"
        }

        Contexto:
        - Empresa ID: $companyId
        - Tabelas permitidas: $tablesCsv
        - Metadados:
        $columnsText

        Pergunta do usuário: "$userQuestion"
        PROMPT;
    }

    /**
     * Prepara a query para execução: valida, proíbe injecções simples e garante company_id binding.
     * Retorna ['rejected'=>bool, 'reason' =>..., 'sql'=>..., 'bindings'=>[]]
     */
    protected function prepareQueryWithCompanyId(string $query, $companyId): array
    {
        $qLower = strtolower($query);

        // Regras básicas de rejeição
        if (!str_starts_with(trim($qLower), 'select')) {
            return ['rejected' => true, 'reason' => 'not_select'];
        }
        if (preg_match('/\b(delete|update|insert|drop|alter|create|truncate)\b/', $qLower)) {
            return ['rejected' => true, 'reason' => 'dml_detected'];
        }
        if (preg_match('/[;]|--|\/\*|\*\//', $qLower)) {
            return ['rejected' => true, 'reason' => 'suspicious_tokens'];
        }

        // Check whitelist: any table mentioned in query must be in allowed list
        $allowed = array_map('strtolower', $this->tablesToAnalyze);
        preg_match_all('/from\s+([\w_]+)/i', $query, $fromMatches);
        preg_match_all('/join\s+([\w_]+)/i', $query, $joinMatches);
        $mentioned = array_merge($fromMatches[1] ?? [], $joinMatches[1] ?? []);
        foreach ($mentioned as $m) {
            if (!in_array(strtolower($m), $allowed)) {
                return ['rejected' => true, 'reason' => "table_not_allowed: {$m}"];
            }
        }

        // Garantir que a query tem company_id: iremos anexar param seguro
        $hasCompanyId = strpos($qLower, 'company_id') !== false;

        $finalSql = $query;
        $bindings = [];

        if (!$hasCompanyId) {
            // Tenta inserir WHERE/AND no final
            if (preg_match('/where\s+/i', $query)) {
                $finalSql = rtrim($query, " \t\n;") . " AND company_id = ?";
            } else {
                $finalSql = rtrim($query, " \t\n;") . " WHERE company_id = ?";
            }
            $bindings[] = $companyId;
        } else {
            // Se já tem company_id, não mexemos — mas substituímos se houver placeholder
            // Se houver um número fixo diferente do companyId, deixamos como está (a IA é responsável)
            // Não vamos tentar parsear e substituir value complexos aqui.
        }

        return ['rejected' => false, 'sql' => $finalSql, 'bindings' => $bindings];
    }

    /**
     * Realiza busca de mercado usando SerpAPI (opcional). Se a chave não existir, retorna [] silenciosamente.
     * Você pode trocar para outro provedor se preferir.
     */
    // protected function performMarketSearch(string $query): array
    // {
    //     $apiKey = env('SERPAPI_KEY');
    //     if (empty($apiKey)) {
    //         return [];
    //     }

    //     // Exemplo usando SerpAPI (Google) — adapte params conforme sua conta
    //     $resp = Http::timeout(15)->get('https://serpapi.com/search.json', [
    //         'q' => $query,
    //         'api_key' => $apiKey,
    //         'engine' => 'google'
    //     ]);

    //     if ($resp->failed()) {
    //         Log::warning('SerpAPI failed: ' . $resp->body());
    //         return [];
    //     }

    //     $json = $resp->json();

    //     // Extrair resultados úteis brevemente (titles + snippets + link)
    //     $out = [];
    //     foreach ($json['organic_results'] ?? [] as $r) {
    //         $out[] = [
    //             'title' => $r['title'] ?? null,
    //             'snippet' => $r['snippet'] ?? null,
    //             'link' => $r['link'] ?? null
    //         ];
    //     }

    //     return $out;
    // }

    protected function performMarketSearch(string $userQuestion, string $context)
    {
        $apiKey = env('HUGGINGFACE_API_KEY');

        if (empty($apiKey)) return [];
        
        try {
            $prompt = <<<PROMPT
            Você é um assistente de pesquisa.  
            Pesquise informações relevantes e atualizadas na internet sobre o tema: "$userQuestion".  
            Use o CONTEXTO abaixo apenas para entender melhor o que deve ser pesquisado, sem mencioná-lo na resposta.

            CONTEXTO: "$context"

            Regras importantes:
            - Priorize resultados de fontes brasileiras, confiáveis e recentes.  
            - Se não houver informações suficientes no Brasil, inclua dados internacionais que façam sentido ao tema.  
            - Retorne apenas informações factuais, claras e objetivas, organizadas de forma estruturada (ex.: tópicos, lista ou resumo).  
            - Evite resultados genéricos ou irrelevantes ao contexto (ex.: se o contexto for sobre produtos hospitalares, não traga fornecedores de produtos eletrônicos ou de uso comum).  
            - Não mencione nada sobre IA, sistema, banco de dados ou instruções internas.  
            - A resposta deve parecer uma pesquisa feita por um humano, com foco em utilidade prática.

            PROMPT;

            error_log('$userQuestion  >>>>>>   ' . $userQuestion);
            error_log('$context >>>>>   ' . $context);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])->post('https://router.huggingface.co/v1/chat/completions', [
                    'model' => $this->synthModel,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ]
                ]);
            // error_log('$response' . $response);

            return $response;
        } catch (\Exception $e) {
            Log::warning('AI search failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Constrói prompt para a IA menor que irá sintetizar (mesclar) respostas internas + externas.
     */
    protected function buildSynthPrompt(string $userQuestion, array $queriesResults, string $internetResults): string
    {
        $dbResultsJson = json_encode($queriesResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
        Você é o NautaIA (Consultor Empresarial) e deve responder a pergunta do usuário com base no dados e sem SUJAR a resposta com lero lero.

        Regras:
        - Entenda qual a principal fonte de informação de acordo com a pergunta.
        - Priorize os dados internos (Resultados do Banco) ou Externos dados da internet de acordo com qual faz mais sentido para responder a pergunta e uso a outra fonte de informação para complementar.
        - Se dados internos existirem, baseie a resposta neles e indique insights concisos. NUNCA invente números.
        - Se dados internos estiverem vazios, utilize resultados da internet para tentar responder.
        - Seja direto, curto e vá ao ponto. Evite fluff.
        - Nunca deixe de dar informação útil mesmo que a resposta fique maior
        - Sempre fale em primeira pessoa.
        - Passe a sensação da sua personalidade na resposta. Você o Nauta IA, uma ia amigável e inteligente que ajuda a empresa a explorar o mundo dos negócios.
        - Não mencione que "leu um banco" ou que "acessou o banco" ou  que "Executou sql , query". Apenas entregue o resultado.
        - Se for uma simples listagem pedida pelo usuário, devolva somente a listagem.

        Pergunta: "$userQuestion"

        Resultados do Banco de Dados (JSON):
        $dbResultsJson

        Resultados de Pesquisa de Mercado (internet):
        $internetResults

        Com base nisso, gere uma resposta objetiva e amigável e inteligente em linguagem natural.
        PROMPT;
    }
}


/**
 * Exemplo de Middleware NautaQueryGuard (pode ser registrado no kernel ou route middleware)
 * Este middleware é opcional pois o controller já executa validações, mas é recomendado
 * para camada adicional de proteção.
 */
class NautaQueryGuard
{
    protected array $allowedTables = [
        'contact_entities',
        'inventory_movements',
        'movement_type',
        'partners',
        'product_categories',
        'products',
        'product_units',
        'warehouses'
    ];

    public function handle($request, \Closure $next)
    {
        // Poderia validar o payload aqui (ex: debug param, question, etc). Como exemplo, apenas segue
        return $next($request);
    }
}


// Fim do arquivo
