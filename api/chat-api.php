<?php
/**
 * JMbenga Portfolio — Chatbot API (Refactored v2)
 * Rate limiting, validação de histórico, cache de contexto, anti-injection, logs.
 */

declare(strict_types=1);

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

// ── Headers ─────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (!str_contains($contentType, 'application/json')) {
    http_response_code(415);
    echo json_encode(['error' => 'Content-Type deve ser application/json.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Rate Limiting (por IP, via sessão/ficheiro) ───────────
function checkRateLimit(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = 'chatbot_rl_' . preg_replace('/[^a-z0-9]/i', '_', $ip);
    $now = time();

    // Usar ficheiro em /tmp para rate limiting (funciona sem memcached/redis)
    $file = sys_get_temp_dir() . '/' . $key . '.json';
    $data = ['count' => 0, 'reset' => $now + CHAT_RATE_LIMIT_WINDOW];

    if (is_file($file) && is_readable($file)) {
        $raw = @file_get_contents($file);
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
    }

    if ($data['reset'] < $now) {
        $data = ['count' => 0, 'reset' => $now + CHAT_RATE_LIMIT_WINDOW];
    }

    $data['count']++;
    @file_put_contents($file, json_encode($data), LOCK_EX);

    if ($data['count'] > CHAT_RATE_LIMIT_REQUESTS) {
        $remaining = max(0, $data['reset'] - $now);
        return "Muitas mensagens. Aguarde {$remaining}s.";
    }

    return null;
}

$rateLimitError = checkRateLimit();
if ($rateLimitError) {
    http_response_code(429);
    header('Retry-After: ' . CHAT_RATE_LIMIT_WINDOW);
    echo json_encode(['error' => $rateLimitError], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Input parsing ───────────────────────────────────────────
$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Pedido JSON inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userMessage = trim((string) ($input['message'] ?? ''));
$clientHistory = $input['history'] ?? [];
$streamMode = !empty($input['stream']);

if ($userMessage === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Mensagem vazia.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (mb_strlen($userMessage) > CHAT_MAX_INPUT_LENGTH) {
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
        if (mb_strlen($text) > 2000) {
            $text = mb_substr($text, 0, 1997) . '...';
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
        return 'chatbot_ctx_' . md5(implode(':', $counts));
    } catch (PDOException $e) {
        return 'chatbot_ctx_' . time();
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

    $years = (int) $cfg('years_experience', '3');
    $clients = (int) $cfg('clients_count', '100');
    $projectCount = (int) $cfg('projects_count', '50');

    try {
        $publishedProjects = (int) $pdo->query("SELECT COUNT(*) FROM _projects WHERE status_project = 'published'")->fetchColumn();
        $projectCount = max($projectCount, $publishedProjects);
    } catch (PDOException $e) {
        error_log('[chatbot] ' . $e->getMessage());
    }

    $aboutSummary = $cfg(
        'about_summary',
        "Sou Jose Mbenga, desenvolvedor Full Stack com mais de {$years} anos de experiencia a criar solucoes digitais inovadoras."
    );

    $aboutLong = $cfg(
        'about_long',
        "Jose Mbenga da Costa e um desenvolvedor Full Stack de Luanda, Angola. Comecou com HTML e CSS, evoluiu para JavaScript, PHP e MySQL, e tambem trabalha com React e Node.js."
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
        'Desenvolvimento Web: sites profissionais, landing pages, sistemas web e plataformas responsivas.',
        'Aplicacoes Web/PWA: experiencias instalaveis, rapidas e com comportamento proximo de app nativo.',
        'APIs e Backend: PHP, MySQL, arquitetura de servidores, integracoes e bases de dados.',
        'UI/UX Design: interfaces modernas, intuitivas e focadas na experiencia do utilizador.',
        'Manutencao e melhoria: correcao de bugs, optimizacao, seguranca e evolucao de sistemas existentes.',
    ];

    $lines = [
        'IDENTIDADE DO PORTFOLIO',
        '- Nome: Jose Mbenga da Costa, também apresentado como JMbenga.',
        '- Perfil: Desenvolvedor Full Stack e UI/UX Designer.',
        '- Localizacao: ' . chatbot_clean_text($cfg('location', 'Luanda, Angola'), 120) . '.',
        "- Experiencia: mais de {$years} anos em desenvolvimento web full stack.",
        "- Historico: mais de {$projectCount} projectos e mais de {$clients} clientes/colaboracoes.",
        '- Resumo: ' . chatbot_clean_text($aboutSummary, 900),
        '- Biografia: ' . chatbot_clean_text($aboutLong, 1200),
        '',
        'SERVICOS',
    ];

    foreach ($services as $service) {
        $lines[] = '- ' . $service;
    }

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
        'Nao use markdown pesado, negrito com asteriscos ou titulos exagerados; prefira texto simples que fique bem dentro do balao do chat.',
        'Use apenas os factos do CONTEXTO DO PORTFOLIO e do historico da conversa. Nao invente dados, certificacoes, empresas, cargos, datas ou tecnologias que nao estejam no contexto.',
        'Nunca use placeholders como [mencione], [insira], [exemplo] ou texto de template. Se uma informacao nao estiver disponivel, diga isso de forma transparente e sugira visitar a pagina adequada ou entrar em contacto.',
        'Se o utilizador responder apenas "sim", "ok", "quero" ou algo parecido, continue a partir da ultima pergunta do historico e aprofunde o assunto anterior.',
        "CONTEXTO DO PORTFOLIO:\n" . buildContextWithCache($pdo),
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
            ->execute([$ip, mb_substr($userMessage, 0, 2000), mb_substr($botResponse, 0, 4000), $durationMs, $status, $ua]);
    } catch (Throwable $e) {
        error_log('[CHATBOT LOG] ' . $e->getMessage());
    }
}

// ── Execução ────────────────────────────────────────────────
$startTime = hrtime(true);
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

try {
    $client = new GeminiClient();
    $result = $client->generate($userMessage, $history, [
        'system_prompt' => chatbot_build_system_prompt($pdo),
        'temperature' => 0.35,
        'max_output_tokens' => 900,
        'max_history_items' => CHAT_MAX_HISTORY_ITEMS,
    ]);

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
    http_response_code($httpCode);
    echo json_encode(['error' => $result['error']], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    $durationMs = (int) round((hrtime(true) - $startTime) / 1e6);
    logChatbot($clientIp, $userMessage, '', $durationMs, 'exception: ' . $e->getMessage());
    error_log('[CHATBOT] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno. Tente novamente mais tarde.'], JSON_UNESCAPED_UNICODE);
}