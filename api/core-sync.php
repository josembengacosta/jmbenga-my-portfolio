<?php

declare(strict_types=1);

/**
 * JMbenga Portfolio — Chatbot API (Refactored v2)
 * Rate limiting, validação de histórico, cache de contexto, anti-injection, logs.
 */

// Headers HTTP e CORS. Só autoriza o domínio público conhecido.
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$allowedOrigins = ['https://jmbenga.freedev.app'];
if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
}

// Responder ao preflight antes de validar o Content-Type.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(($origin === '' || in_array($origin, $allowedOrigins, true)) ? 204 : 403);
    exit;
}

// Inclusão dos ficheiros do sistema
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/gemini_client.php';

// ── Configurações ───────────────────────────────────────────
const CHAT_RATE_LIMIT_REQUESTS = 12;   // requests
const CHAT_RATE_LIMIT_WINDOW   = 60;   // segundos
const CHAT_MAX_INPUT_LENGTH    = 600;  // caracteres
const CHAT_CONTEXT_CACHE_TTL   = 300;  // 5 minutos
const CHAT_MAX_HISTORY_ITEMS   = 10;
const CHAT_MAX_HISTORY_AGE     = 1800; // 30 minutos

// ── Validação do pedido ─────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST, OPTIONS');
    echo json_encode(['error' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (!str_contains($contentType, 'application/json')) {
    http_response_code(415);
    echo json_encode(['error' => 'Content-Type deve ser application/json.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Rate limiting por IP, guardado fora da pasta pública ───
function checkRateLimit(string $ip): array
{
    $now = time();
    $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'jmbenga-chat-rate-' . hash('sha256', $ip) . '.json';
    $handle = @fopen($path, 'c+');

    // Se o armazenamento não estiver disponível, falhar em segurança.
    if ($handle === false) {
        return ['ok' => false, 'limited' => false, 'retry_after' => 0];
    }
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        return ['ok' => false, 'limited' => false, 'retry_after' => 0];
    }

    $raw = stream_get_contents($handle);
    $state = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($state) || !isset($state['reset'], $state['count']) || (int) $state['reset'] <= $now) {
        $state = ['count' => 0, 'reset' => $now + CHAT_RATE_LIMIT_WINDOW];
    }

    $state['count'] = max(0, (int) $state['count']) + 1;
    $retryAfter = max(1, (int) $state['reset'] - $now);
    rewind($handle);
    ftruncate($handle, 0);
    $written = fwrite($handle, json_encode($state));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    if ($written === false) {
        return ['ok' => false, 'limited' => false, 'retry_after' => 0];
    }

    return [
        'ok' => true,
        'limited' => $state['count'] > CHAT_RATE_LIMIT_REQUESTS,
        'retry_after' => $retryAfter,
    ];
}

$clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$rateLimit = checkRateLimit($clientIp);
if (!$rateLimit['ok']) {
    http_response_code(503);
    echo json_encode(['error' => 'O assistente está temporariamente indisponível. Tente novamente.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($rateLimit['limited']) {
    http_response_code(429);
    header('Retry-After: ' . $rateLimit['retry_after']);
    echo json_encode(['error' => 'Muitas mensagens num curto período. Aguarde antes de tentar novamente.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Input parsing ───────────────────────────────────────────
$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || strlen($rawBody) > 32768) {
    http_response_code(413);
    echo json_encode(['error' => 'Pedido demasiado grande.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$input = json_decode($rawBody, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Pedido JSON inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($input['message']) || !is_string($input['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'O campo message deve ser texto.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userMessage = trim($input['message']);
$clientHistory = $input['history'] ?? [];
// Este endpoint mantém o contrato JSON síncrono. O modo stream só deve ser
// activado depois de o cliente JavaScript e o GeminiClient suportarem SSE.
if (!is_array($clientHistory)) {
    http_response_code(400);
    echo json_encode(['error' => 'O histórico da conversa é inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($userMessage === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Mensagem vazia.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ((function_exists('mb_strlen') ? mb_strlen($userMessage, 'UTF-8') : strlen($userMessage)) > CHAT_MAX_INPUT_LENGTH) {
    http_response_code(400);
    echo json_encode(['error' => 'Mensagem muito longa. Máximo ' . CHAT_MAX_INPUT_LENGTH . ' caracteres.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Anti prompt-injection básico ──────────────────────────
$blockedPatterns = [
    '/ignore\s+(all\s+)?previous\s+instructions/i',
    '/ignore\s+(the\s+)?system\s+prompt/i',
    '/you\s+are\s+now\s+/i',
    '/api\s*key/i',
    '/GEMINI_API_KEY/i',
    '/prompt\s*injection/i',
    '/DAN\s+mode/i',
    '/jailbreak/i',
];

foreach ($blockedPatterns as $pattern) {
    if (preg_match($pattern, $userMessage)) {
        logChatbot($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $userMessage, '', 0, 'blocked_injection');
        http_response_code(400);
        echo json_encode(['error' => 'Não foi possível processar esta mensagem.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ── Validação e sanitização do histórico ──────────────────
function sanitizeHistory(array $history): array
{
    $out = [];
    foreach ($history as $item) {
        if (!is_array($item)) continue;
        $role = strtolower((string) ($item['role'] ?? 'user'));
        if (!in_array($role, ['user', 'model', 'assistant'], true)) continue;
        $role = in_array($role, ['model', 'assistant'], true) ? 'model' : 'user';

        $text = trim((string) ($item['text'] ?? $item['content'] ?? $item['message'] ?? ''));
        if ($text === '') continue;
        if ((function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text)) > 2000) {
            $text = (function_exists('mb_substr') ? mb_substr($text, 0, 1997, 'UTF-8') : substr($text, 0, 1997)) . '...';
        }
        $out[] = ['role' => $role, 'text' => $text];
    }
    return array_slice($out, -CHAT_MAX_HISTORY_ITEMS);
}

$history = sanitizeHistory($clientHistory);

// ── Cache de contexto do portfolio ──────────────────────────
function getContextCacheKey(PDO $pdo): string
{
    // Hash baseado nos counts das tabelas relevantes (barato e eficaz)
    try {
        $counts = [
            (int) $pdo->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published'")->fetchColumn(),
            (int) $pdo->query("SELECT COUNT(*) FROM _skills WHERE is_visible = 1")->fetchColumn(),
            (int) $pdo->query("SELECT COUNT(*) FROM _faq WHERE status_faq = 'visible'")->fetchColumn(),
            (int) $pdo->query("SELECT COUNT(*) FROM _config WHERE 1")->fetchColumn(),
        ];
        return 'chatbot_ctx_v3_' . md5(implode(':', $counts));
    } catch (PDOException $e) {
        return 'chatbot_ctx_v3_' . time();
    }
}

function buildContextWithCache(PDO $pdo): string
{
    $cacheKey = getContextCacheKey($pdo);
    $cacheFile = sys_get_temp_dir() . '/' . $cacheKey . '.txt';

    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < CHAT_CONTEXT_CACHE_TTL) {
        $cached = @file_get_contents($cacheFile);
        if ($cached !== false && $cached !== '') {
            return $cached;
        }
    }

    $context = chatbot_build_context($pdo);
    @file_put_contents($cacheFile, $context, LOCK_EX);
    return $context;
}

// ── Builders existentes (mantidos, com pequenas melhorias) ──
function chatbot_clean_text($value, $maxLength = 600): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)));
    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > $maxLength) {
        // Truncar por palavra completa
        $truncated = mb_substr($text, 0, $maxLength, 'UTF-8');
        $lastSpace = mb_strrpos($truncated, ' ', 0, 'UTF-8');
        if ($lastSpace !== false && $lastSpace > $maxLength * 0.8) {
            $truncated = mb_substr($truncated, 0, $lastSpace, 'UTF-8');
        }
        return $truncated . '...';
    }
    if (!function_exists('mb_strlen') && strlen($text) > $maxLength) {
        $truncated = substr($text, 0, $maxLength);
        $lastSpace = strrpos($truncated, ' ');
        if ($lastSpace !== false && $lastSpace > $maxLength * 0.8) {
            $truncated = substr($truncated, 0, $lastSpace);
        }
        return $truncated . '...';
    }
    return $text;
}

function chatbot_fetch_all(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('[chatbot] ' . $e->getMessage());
        return [];
    }
}

function chatbot_build_context(PDO $pdo): string
{
    $cfg = function (string $key, string $default = '') use ($pdo): string {
        return get_config($pdo, $key, $default);
    };

    // Usar dados confirmados: não inventar anos de experiência, clientes ou
    // contagens promocionais quando os valores não estiverem comprovados.
    $publishedProjects = 0;
    try {
        $publishedProjects = (int) $pdo->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published'")->fetchColumn();
    } catch (PDOException $e) {
        error_log('[chatbot] Não foi possível contar projectos publicados: ' . $e->getMessage());
    }

    $aboutSummary = $cfg(
        'about_summary',
        'Sou José Mbenga, também conhecido como JMbenga, e desenvolvo soluções digitais para necessidades concretas.'
    );

    $aboutLong = $cfg(
        'about_long',
        'José Mbenga da Costa é o responsável pelo portfólio JMbenga, com foco em desenvolvimento web, backend e design de interfaces.'
    );

    $skills = chatbot_fetch_all(
        $pdo,
        "SELECT name_skill, category_skill, percentage_skill
         FROM _skills
         WHERE is_visible = 1
         ORDER BY category_skill ASC, display_order ASC
         LIMIT 18"
    );

    $projects = chatbot_fetch_all(
        $pdo,
        "SELECT title_project, category_project, summary_project, slug_project, tech_stack
         FROM _projects
         WHERE status_project = 'published'
         ORDER BY is_featured DESC, display_order ASC, creat_project DESC
         LIMIT 8"
    );

    $faqs = chatbot_fetch_all(
        $pdo,
        "SELECT category_faq, question, answer
         FROM _faq
         WHERE status_faq = 'visible'
         ORDER BY display_order ASC, id_faq DESC
         LIMIT 8"
    );

    $services = [
        'Desenvolvimento Web: sites profissionais, landing pages, sites institucionais, lojas online e sistemas web responsivos.',
        'Aplicações Mobile: desenvolvimento de aplicações móveis; o preço é definido por orçamento personalizado.',
        'APIs e Backend: PHP, MySQL, APIs, integrações e bases de dados. Integração de API: referência de 30.000 a 100.000 Kz; CRUD com base de dados: 300.000 a 550.000 Kz; funcionalidade adicional: a partir de 25.000 Kz; aplicações complexas: a partir de 700.000 Kz.',
        'UI/UX Design: desenho de interfaces e experiência do utilizador. Redesign de site existente: referência de 60.000 a 180.000 Kz; outros trabalhos de design são orçamentados conforme o escopo.',
        'Manutenção e melhoria: correcção de bugs, actualizações e evolução de sistemas existentes. JMbenga Care Basic: 15.000 Kz/mês; Care Business: 35.000 Kz/mês; Care Pro: 70.000 Kz/mês, sujeitos às condições do plano.',
        'Pacotes de Desenvolvimento Web: JMbenga Start: 75.000 a 100.000 Kz; JMbenga Business: 180.000 a 280.000 Kz; JMbenga Custom: a partir de 300.000 Kz.',
        'Outras referências de Desenvolvimento Web: landing page: 60.000 a 100.000 Kz; portfólio profissional: 90.000 a 160.000 Kz; site institucional de 4 a 5 páginas: 180.000 a 280.000 Kz; site empresarial: 280.000 a 450.000 Kz; loja online: 300.000 a 650.000 Kz.',
    ];

    $lines = [
        'IDENTIDADE DO PORTFOLIO',
        '- Nome: Jose Mbenga da Costa, também apresentado como JMbenga.',
        '- Perfil: Desenvolvedor Full Stack com foco em soluções web, backend e interfaces.',
        '- Localização: ' . chatbot_clean_text($cfg('location', 'Luanda, Angola'), 120) . '.',
        '- Projectos publicados na base de dados: ' . $publishedProjects . '.',
        '- Não afirmar número de clientes, anos de experiência, taxa de satisfação ou métricas de desempenho sem confirmação explícita.',
        '- Resumo: ' . chatbot_clean_text($aboutSummary, 900),
        '- Biografia: ' . chatbot_clean_text($aboutLong, 1200),
        '',
        'SERVICOS',
    ];

    foreach ($services as $service) {
        $lines[] = '- ' . $service;
    }

    $lines[] = '';
    $lines[] = 'REGRAS COMERCIAIS';
    $lines[] = '- Os valores acima são referências comerciais do portfólio; confirmar sempre o escopo e o orçamento final antes de prometer um preço.';
    $lines[] = '- Não inventar preços para Aplicações Mobile, UI/UX Design geral ou serviços que não tenham valor de referência definido; encaminhar para orçamento personalizado.';
    $lines[] = '- Domínio, alojamento, licenças e serviços de terceiros são despesas separadas, salvo indicação expressa na proposta.';
    $lines[] = '- Condição habitual proposta: 50% para iniciar e 50% antes da entrega/publicação; projectos maiores podem ter pagamentos por fases.';
    $lines[] = '- O escopo e as revisões devem ficar descritos na proposta. Funcionalidades adicionais não estão automaticamente incluídas em manutenção.';
    $lines[] = '';
    $lines[] = 'SKILLS VISIVEIS';

    if ($skills) {
        foreach ($skills as $skill) {
            $lines[] = '- ' . $skill['name_skill'] . ' (' . $skill['category_skill'] . ', ' . (int) $skill['percentage_skill'] . '%)';
        }
    } else {
        $lines[] = '- HTML5, CSS3, JavaScript, PHP, MySQL, Git, React e Node.js.';
    }

    $lines[] = '';
    $lines[] = 'PROJECTOS PUBLICADOS';

    if ($projects) {
        foreach ($projects as $project) {
            $techStack = '';
            $tech = json_decode((string) $project['tech_stack'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tech) && $tech) {
                $techStack = ' Tecnologias: ' . implode(', ', array_slice($tech, 0, 6)) . '.';
            }
            $lines[] = '- ' . $project['title_project'] . ' (' . $project['category_project'] . '): ' . chatbot_clean_text($project['summary_project'], 260) . $techStack . ' Link: ' . BASE_URL . '/project/' . $project['slug_project'];
        }
    } else {
        $lines[] = '- O portfolio indica projectos em desenvolvimento web, e-commerce, dashboards, APIs e interfaces digitais.';
    }

    $lines[] = '';
    $lines[] = 'FAQS DO SITE';

    foreach ($faqs as $faq) {
        $lines[] = '- [' . $faq['category_faq'] . '] ' . chatbot_clean_text($faq['question'], 180) . ' Resposta: ' . chatbot_clean_text($faq['answer'], 360);
    }

    $lines[] = '';
    $lines[] = 'CONTACTOS E LINKS';
    $lines[] = '- Email: ' . chatbot_clean_text($cfg('email_contact', 'josembengadacosta@gmail.com'), 160);
    $lines[] = '- Telefone: ' . chatbot_clean_text($cfg('phone_contact', '+244 922 030 116'), 80);
    $lines[] = '- WhatsApp: ' . chatbot_clean_text($cfg('whatsapp_url', 'https://wa.me/244922030116'), 180);
    $lines[] = '- GitHub: ' . chatbot_clean_text($cfg('github_url', 'https://github.com/josembengacosta'), 180);
    $lines[] = '- LinkedIn: ' . chatbot_clean_text($cfg('linkedin_url', 'https://linkedin.com/in/josembengadacosta'), 180);

    return implode("\n", $lines);
}

function chatbot_build_system_prompt(PDO $pdo): string
{
    return implode("\n\n", [
        'Voce e o assistente virtual oficial do portfolio JMbenga.',
        'Responda sempre em portugues claro, natural, util e com acentuacao correta.',
        'FORMATO OBRIGATORIO: a interface do chat apresenta texto simples e transforma apenas URLs em links. Nao use Markdown: nunca escreva **negrito**, titulos com #, tabelas Markdown ou blocos de codigo. Organize respostas com titulos curtos em MAIUSCULAS, listas com o marcador • e uma linha em branco entre secoes. Prefira frases curtas e evite paragrafos longos.',
        'Quando listar varias opcoes, agrupe-as por categoria, ponha primeiro a informacao mais relevante e evite repetir o mesmo preco em secoes diferentes. Termine com no maximo uma pergunta util para orientar o visitante.',
        'Use apenas os factos do CONTEXTO DO PORTFOLIO e do historico da conversa. Nao invente dados, certificacoes, empresas, cargos, datas ou tecnologias que nao estejam no contexto.',
        'Para precos, use exclusivamente as referencias comerciais presentes no contexto. Identifique-os como referencias, nao como orcamento definitivo. Se nao houver preco indicado para um servico, explique que requer orcamento personalizado.',
        'Nao afirme numeros de clientes, anos de experiencia, testemunhos, classificacoes, garantias de resultado ou metricas que nao estejam expressamente confirmados no contexto.',
        'Nunca use placeholders como [mencione], [insira], [exemplo] ou texto de template. Se uma informacao nao estiver disponivel, diga isso de forma transparente e sugira visitar a pagina adequada ou entrar em contacto.',
        'Se o utilizador responder apenas "sim", "ok", "quero" ou algo parecido, continue a partir da ultima pergunta do historico e aprofunde o assunto anterior.',
        "CONTEXTO DO PORTFOLIO:\n" . buildContextWithCache($pdo),
    ]);
}

/** Detecta perguntas gerais sobre a lista de precos para devolver um resumo consistente. */
function chatbot_is_general_price_question(string $message): bool
{
    $text = function_exists('mb_strtolower') ? mb_strtolower($message, 'UTF-8') : strtolower($message);
    if (function_exists('iconv')) {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (is_string($ascii)) {
            $text = strtolower($ascii);
        }
    }
    $hasPrice = (bool) preg_match('/\b(preco|precos|valor|valores|custo|custos)\b/', $text);
    if (!$hasPrice) {
        return false;
    }
    return (bool) preg_match(
        '/\b(servico|servicos|pacote|pacotes|catalogo)\b|\b(como sao os precos|quais sao os precos|quais os precos|tabela geral de precos|lista geral de precos|precos em geral)\b/',
        $text
    );
}

/** Resposta curta e organizada para perguntas gerais sobre os precos. */
function chatbot_build_price_overview(): string
{
    return implode("\n", [
        'PREÇOS DE REFERÊNCIA — JMbenga',
        'Os valores abaixo são referências em Kz. O preço final depende do escopo confirmado.',
        '',
        '1. DESENVOLVIMENTO WEB',
        'Pacotes:',
        '• JMbenga Start — 75.000 a 100.000 Kz',
        '• JMbenga Business — 180.000 a 280.000 Kz',
        '• JMbenga Custom — a partir de 300.000 Kz',
        'Projectos comuns:',
        '• Landing page — 60.000 a 100.000 Kz',
        '• Portfólio profissional — 90.000 a 160.000 Kz',
        '• Site institucional (4–5 páginas) — 180.000 a 280.000 Kz',
        '• Site empresarial — 280.000 a 450.000 Kz',
        '• Loja online — 300.000 a 650.000 Kz',
        '• Site de evento — 70.000 a 130.000 Kz',
        '• Painel informativo — 150.000 a 250.000 Kz',
        '• Painel dinâmico — 250.000 a 450.000 Kz',
        '',
        '2. APIs E BACKEND',
        '• Integração de API — 30.000 a 100.000 Kz',
        '• CRUD com base de dados — 300.000 a 550.000 Kz',
        '• Funcionalidade adicional — a partir de 25.000 Kz',
        '• Aplicação complexa — a partir de 700.000 Kz',
        '',
        '3. UI/UX DESIGN',
        '• Redesign de site existente — 60.000 a 180.000 Kz',
        '• Outros trabalhos de UI/UX — orçamento personalizado',
        '',
        '4. MANUTENÇÃO (MENSAL)',
        '• Care Basic — 15.000 Kz/mês',
        '• Care Business — 35.000 Kz/mês',
        '• Care Pro — 70.000 Kz/mês',
        'O âmbito e as horas incluídas dependem das condições de cada plano.',
        '',
        '5. APLICAÇÕES MOBILE',
        '• Orçamento personalizado, conforme as funcionalidades e a complexidade.',
        '',
        'CONDIÇÕES IMPORTANTES',
        '• Os preços são indicativos, não uma proposta definitiva.',
        '• Domínio, alojamento, licenças e serviços de terceiros são cobrados à parte, salvo indicação na proposta.',
        '• A condição habitual é 50% para iniciar e 50% antes da entrega/publicação; projectos maiores podem ser pagos por fases.',
        '',
        'Qual destes serviços pretende explorar primeiro?'
    ]);
}

// ── Logging ─────────────────────────────────────────────────
function logChatbot(string $ip, string $userMessage, string $botResponse, int $durationMs, string $status): void
{
    try {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!$pdo) return;

        $pdo->prepare("INSERT INTO _chatbot_logs (ip_address, user_message, bot_response, duration_ms, status, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())")
            ->execute([$ip, function_exists('mb_substr') ? mb_substr($userMessage, 0, 2000, 'UTF-8') : substr($userMessage, 0, 2000), function_exists('mb_substr') ? mb_substr($botResponse, 0, 4000, 'UTF-8') : substr($botResponse, 0, 4000), $durationMs, $status, $ua]);
    } catch (Throwable $e) {
        error_log('[CHATBOT LOG] ' . $e->getMessage());
    }
}

// ── Execução ────────────────────────────────────────────────
$startTime = hrtime(true);
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

try {
    if (chatbot_is_general_price_question($userMessage)) {
        // Para uma pergunta geral de preços, usar uma resposta comercial única e consistente.
        $result = [
            'ok' => true,
            'text' => chatbot_build_price_overview(),
            'http_code' => 200,
        ];
    } else {
        $client = new \GeminiClient();
        $result = $client->generate($userMessage, $history, [
            'system_prompt' => chatbot_build_system_prompt($pdo),
            'temperature' => 0.35,
            'max_output_tokens' => 900,
            'max_history_items' => CHAT_MAX_HISTORY_ITEMS,
        ]);
    }

    $durationMs = (int) round((hrtime(true) - $startTime) / 1e6);

    if ($result['ok']) {
        logChatbot($clientIp, $userMessage, $result['text'], $durationMs, 'success');
        echo json_encode(['response' => $result['text']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $httpCode = (int) ($result['http_code'] ?? 500);
    if ($httpCode < 400 || $httpCode > 599) {
        $httpCode = 500;
    }

    logChatbot($clientIp, $userMessage, '', $durationMs, 'error: ' . $result['error']);
    error_log('[CHATBOT] Falha do provedor: ' . (string) ($result['error'] ?? 'erro desconhecido'));
    http_response_code($httpCode >= 500 ? 502 : $httpCode);
    echo json_encode([
        'error' => $httpCode >= 500
            ? 'O assistente não conseguiu responder agora. Tente novamente dentro de instantes.'
            : 'Não foi possível processar a mensagem. Reveja o pedido e tente novamente.'
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $durationMs = (int) round((hrtime(true) - $startTime) / 1e6);
    logChatbot($clientIp, $userMessage, '', $durationMs, 'exception: ' . $e->getMessage());
    error_log('[CHATBOT] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno. Tente novamente mais tarde.'], JSON_UNESCAPED_UNICODE);
}