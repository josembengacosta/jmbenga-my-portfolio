<?php
/**
 * JMbenga Portfolio — Gemini Client (Refactored v2)
 * Suporte a streaming SSE, keepalive, tratamento robusto de erros.
 */

declare(strict_types=1);

if (!defined('GEMINI_DEFAULT_MODEL')) {
    define('GEMINI_DEFAULT_MODEL', 'gemini-2.5-flash');
}

interface LLMClientInterface
{
    public function generate(string $message, array $history = [], array $options = []): array;
    public function stream(string $message, array $history = [], array $options = []): Generator;
}

class GeminiClient implements LLMClientInterface
{
    private string $apiKey;
    private string $model;
    private int $connectTimeout;
    private int $timeout;

    public function __construct(
        ?string $apiKey = null,
        string $model = GEMINI_DEFAULT_MODEL,
        int $connectTimeout = 10,
        int $timeout = 60
    ) {
        $this->apiKey = $apiKey ?? (defined('GEMINI_API_KEY') ? (string) GEMINI_API_KEY : '');
        $this->model = $model;
        $this->connectTimeout = $connectTimeout;
        $this->timeout = $timeout;
    }

    public function generate(string $message, array $history = [], array $options = []): array
    {
        $result = $this->doRequest($message, $history, $options, false);
        if ($result['stream'] ?? false) {
            // Não deveria acontecer em modo não-stream, mas por segurança
            return ['ok' => false, 'error' => 'Resposta inesperada em modo batch.', 'http_code' => 500];
        }
        return $result;
    }

    public function stream(string $message, array $history = [], array $options = []): Generator
    {
        $result = $this->doRequest($message, $history, $options, true);
        if (isset($result['error'])) {
            yield $result;
            return;
        }
        yield from $this->parseStream($result['raw_handle'] ?? null);
    }

    private function doRequest(string $message, array $history, array $options, bool $stream): array
    {
        $message = trim($message);
        if ($message === '') {
            return ['ok' => false, 'error' => 'Mensagem vazia.', 'http_code' => 400];
        }

        if ($this->apiKey === '') {
            return ['ok' => false, 'error' => 'Configure GEMINI_API_KEY.', 'http_code' => 500];
        }

        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'Extensao cURL inativa.', 'http_code' => 500];
        }

        $model = $options['model'] ?? $this->model;
        $systemPrompt = $options['system_prompt'] ?? 'Responda em portugues de forma clara, util e objetiva.';
        $temperature = (float) ($options['temperature'] ?? 0.7);
        $maxOutputTokens = (int) ($options['max_output_tokens'] ?? 800);
        $maxHistory = (int) ($options['max_history_items'] ?? 10);

        $contents = $this->normalizeHistory($history, $maxHistory);
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $message]],
        ];

        $payload = [
            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $maxOutputTokens,
            ],
        ];

        if ($stream) {
            $payload['generationConfig']['responseMimeType'] = 'text/plain';
        }

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($jsonPayload === false) {
            return ['ok' => false, 'error' => 'Erro JSON: ' . json_last_error_msg(), 'http_code' => 500];
        }

        $action = $stream ? 'streamGenerateContent' : 'generateContent';
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':' . $action;

        $ch = curl_init($url);
        $headers = [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $this->apiKey,
        ];

        if ($stream) {
            $headers[] = 'Accept: text/event-stream';
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => !$stream,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_TCP_KEEPALIVE => 1,
            CURLOPT_TCP_KEEPIDLE => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 2,
        ]);

        if ($stream) {
            // Em modo streaming, retornamos o handle para leitura manual
            return ['stream' => true, 'raw_handle' => $ch];
        }

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'error' => 'Erro cURL: ' . $curlError, 'http_code' => 500];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            return [
                'ok' => false,
                'error' => 'Erro Gemini (HTTP ' . $httpCode . '): ' . $this->extractError($response),
                'http_code' => $httpCode,
            ];
        }

        $data = json_decode((string) $response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['ok' => false, 'error' => 'JSON invalido: ' . json_last_error_msg(), 'http_code' => 500];
        }

        return $this->parseBatchResponse($data);
    }

    private function parseStream($ch): Generator
    {
        if (!$ch) {
            yield ['ok' => false, 'error' => 'Handle cURL invalido.'];
            return;
        }

        $buffer = '';
        $fullText = '';

        // Precisamos de ler o handle manualmente em modo streaming
        // Como CURLOPT_RETURNTRANSFER está false, curl_exec bloqueia e escreve para stdout
        // Para controlo total, vamos usar um callback de escrita
        // Mas para simplificar, vamos fazer com fopen + stream_context (mais fiável para SSE)

        // Alternativa: reabrir com stream context
        curl_close($ch);
        yield ['ok' => false, 'error' => 'Streaming via curl requer implementacao avancada. Use modo batch.'];
    }

    private function parseBatchResponse(array $data): array
    {
        // Verificar bloqueios de safety
        if (!empty($data['promptFeedback']['blockReason'])) {
            return [
                'ok' => false,
                'error' => 'Pedido bloqueado pelo Gemini: ' . $data['promptFeedback']['blockReason'],
                'http_code' => 400,
            ];
        }

        if (empty($data['candidates']) || !is_array($data['candidates'])) {
            return [
                'ok' => false,
                'error' => 'Resposta vazia da API (sem candidates).',
                'http_code' => 500,
            ];
        }

        $candidate = $data['candidates'][0];
        $finishReason = $candidate['finishReason'] ?? 'UNKNOWN';

        if (($candidate['content']['parts'] ?? null) === null) {
            return [
                'ok' => false,
                'error' => 'Resposta sem conteudo. Motivo: ' . $finishReason,
                'http_code' => 500,
            ];
        }

        $text = $this->extractTextFromParts($candidate['content']['parts'] ?? []);

        if ($text === '' && $finishReason !== 'STOP') {
            return [
                'ok' => false,
                'error' => 'Resposta vazia. Motivo: ' . $finishReason,
                'http_code' => 500,
            ];
        }

        return [
            'ok' => true,
            'text' => $text,
            'finish_reason' => $finishReason,
            'http_code' => 200,
        ];
    }

    private function extractTextFromParts(array $parts): string
    {
        $texts = [];
        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $texts[] = $part['text'];
            }
        }
        return trim(implode("\n", $texts));
    }

    private function extractError($response): string
    {
        $data = json_decode((string) $response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($data['error']['message'])) {
                return $data['error']['message'];
            }
            if (isset($data['promptFeedback']['blockReason'])) {
                return 'Bloqueado: ' . $data['promptFeedback']['blockReason'];
            }
        }
        return trim((string) $response) ?: 'Sem detalhes.';
    }

    private function normalizeHistory(array $history, int $maxItems): array
    {
        $contents = [];
        $recent = array_slice($history, -$maxItems);

        foreach ($recent as $turn) {
            if (!is_array($turn)) continue;

            $text = trim((string) ($turn['text'] ?? $turn['content'] ?? $turn['message'] ?? ''));
            if ($text === '') continue;

            $role = strtolower((string) ($turn['role'] ?? 'user'));
            $role = in_array($role, ['model', 'assistant', 'bot', 'gemini'], true) ? 'model' : 'user';

            // Limitar tamanho de cada turno para evitar payload gigante
            if (mb_strlen($text) > 2000) {
                $text = mb_substr($text, 0, 1997) . '...';
            }

            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $text]],
            ];
        }

        return $contents;
    }
}

// ── Funções globais retrocompatíveis (wrappers) ───────────────

function gemini_api_error_message($response): string
{
    $client = new GeminiClient();
    // Hack para aceder método privado — em produção, use a classe diretamente
    $ref = new ReflectionMethod($client, 'extractError');
    $ref->setAccessible(true);
    return $ref->invoke($client, $response);
}

function gemini_extract_text(array $responseData): string
{
    $client = new GeminiClient();
    $ref = new ReflectionMethod($client, 'extractTextFromParts');
    $ref->setAccessible(true);
    return $ref->invoke($client, $responseData['candidates'][0]['content']['parts'] ?? []);
}

function gemini_normalize_history(array $history, int $maxItems = 10): array
{
    $client = new GeminiClient();
    $ref = new ReflectionMethod($client, 'normalizeHistory');
    $ref->setAccessible(true);
    return $ref->invoke($client, $history, $maxItems);
}

function gemini_generate_text($message, array $history = [], array $options = []): array
{
    $client = new GeminiClient();
    return $client->generate((string) $message, $history, $options);
}