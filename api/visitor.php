<?php
declare(strict_types=1);

/**
 * api/visitor.php — POST (JSON body)
 * Recebe eventos complementares do visitor-tracker.js.
 * O registo inicial da pageview é feito em includes/visitor.php; esta API trata
 * apenas de presença e tempo na página. Resposta silenciosa HTTP 204.
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$finish = static function (): void {
    http_response_code(204);
    exit;
};


/** Faz uma consulta HTTPS curta ao serviço de geolocalização para um IP público. */
if (!function_exists('jmbenga_visitor_geo_lookup')) {
    function jmbenga_visitor_geo_lookup(string $ip): ?array
    {
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        if (filter_var($ip, FILTER_VALIDATE_IP, $flags) === false) {
            return null;
        }

        $url = 'https://ipwho.is/' . rawurlencode($ip);
        $body = false;
        $status = 0;

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            if ($curl !== false) {
                $responseBody = '';
                curl_setopt_array($curl, [
                    CURLOPT_RETURNTRANSFER => false,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_CONNECTTIMEOUT => 2,
                    CURLOPT_TIMEOUT => 4,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_HTTPHEADER => ['Accept: application/json'],
                    CURLOPT_USERAGENT => 'JMbengaPortfolio-VisitorGeo/1.0',
                    CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$responseBody): int {
                        $length = strlen($chunk);
                        if (strlen($responseBody) + $length > 16384) {
                            return 0;
                        }
                        $responseBody .= $chunk;
                        return $length;
                    },
                ]);

                $result = curl_exec($curl);
                $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                curl_close($curl);

                if ($result !== false) {
                    $body = $responseBody;
                }
            }
        } elseif (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 4,
                    'ignore_errors' => true,
                    'header' => "Accept: application/json\r\nUser-Agent: JMbengaPortfolio-VisitorGeo/1.0\r\n",
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $stream = @fopen($url, 'rb', false, $context);
            if (is_resource($stream)) {
                $body = stream_get_contents($stream, 16385);
                fclose($stream);
            }

            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $headerLine) {
                    if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $headerLine, $matches)) {
                        $status = (int) $matches[1];
                    }
                }
            }
        }

        if (!is_string($body) || $body === '' || strlen($body) > 16384 || $status !== 200) {
            return null;
        }

        $response = json_decode($body, true, 16);
        if (!is_array($response) || ($response['success'] ?? false) !== true) {
            return null;
        }

        $requestedIp = @inet_pton($ip);
        $returnedIp = isset($response['ip']) && is_string($response['ip'])
            ? @inet_pton($response['ip'])
            : false;
        if ($requestedIp === false || $returnedIp === false || $requestedIp !== $returnedIp) {
            return null;
        }

        $cleanText = static function ($value, int $maxLength): ?string {
            if (!is_string($value)) {
                return null;
            }
            $value = trim(strip_tags($value));
            if ($value === '') {
                return null;
            }
            return function_exists('mb_substr')
                ? mb_substr($value, 0, $maxLength, 'UTF-8')
                : substr($value, 0, $maxLength);
        };

        $connection = is_array($response['connection'] ?? null) ? $response['connection'] : [];
        $timezoneData = is_array($response['timezone'] ?? null) ? $response['timezone'] : [];
        $countryCode = $cleanText($response['country_code'] ?? null, 2);
        if ($countryCode !== null && !preg_match('/^[A-Z]{2}$/i', $countryCode)) {
            $countryCode = null;
        }

        $latitude = is_numeric($response['latitude'] ?? null) ? (float) $response['latitude'] : null;
        if ($latitude !== null && ($latitude < -90 || $latitude > 90)) {
            $latitude = null;
        }
        $longitude = is_numeric($response['longitude'] ?? null) ? (float) $response['longitude'] : null;
        if ($longitude !== null && ($longitude < -180 || $longitude > 180)) {
            $longitude = null;
        }

        $timezone = $cleanText($timezoneData['id'] ?? null, 50);
        if ($timezone !== null) {
            try {
                new DateTimeZone($timezone);
            } catch (Throwable $error) {
                $timezone = null;
            }
        }

        return [
            'country_code' => $countryCode,
            'country_name' => $cleanText($response['country'] ?? null, 100),
            'city' => $cleanText($response['city'] ?? null, 100),
            'region' => $cleanText($response['region'] ?? null, 100),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timezone' => $timezone,
            'isp' => $cleanText($connection['isp'] ?? $connection['org'] ?? null, 255),
        ];
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

// Validar Origin/Referer quando o navegador os envia. A sessão pública e a
// associação id_visitor + visitor_session_id continuam a ser a autorização efectiva.
$expectedScheme = strtolower((string) parse_url((string) BASE_URL, PHP_URL_SCHEME));
if (!in_array($expectedScheme, ['http', 'https'], true)) {
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    $expectedScheme = ($https !== '' && !in_array($https, ['off', '0'], true)) ? 'https' : 'http';
}
$expectedHostParts = parse_url($expectedScheme . '://' . (string) ($_SERVER['HTTP_HOST'] ?? ''));
if (!is_array($expectedHostParts) || empty($expectedHostParts['host'])) {
    $finish();
}
$expectedHost = strtolower((string) $expectedHostParts['host']);
$expectedPort = (int) ($expectedHostParts['port'] ?? ($expectedScheme === 'https' ? 443 : 80));

$sourceUrl = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
if ($sourceUrl === '') {
    $sourceUrl = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
}
if ($sourceUrl !== '') {
    $sourceParts = parse_url($sourceUrl);
    if (!is_array($sourceParts) || empty($sourceParts['host'])) {
        $finish();
    }
    $sourceScheme = strtolower((string) ($sourceParts['scheme'] ?? ''));
    $sourcePort = (int) ($sourceParts['port'] ?? ($sourceScheme === 'https' ? 443 : 80));
    if (strtolower((string) $sourceParts['host']) !== $expectedHost
        || $sourceScheme !== $expectedScheme
        || $sourcePort !== $expectedPort) {
        $finish();
    }
}

$contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
if (!preg_match('~^application/json(?:\s*;|$)~i', $contentType)) {
    $finish();
}

$maxBodyBytes = 8192;
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > $maxBodyBytes) {
    $finish();
}
$rawBody = file_get_contents('php://input', false, null, 0, $maxBodyBytes + 1);
if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > $maxBodyBytes) {
    $finish();
}
$data = json_decode($rawBody, true, 16);
if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
    $finish();
}

$publicSessionName = defined('PUBLIC_SESSION_NAME') ? PUBLIC_SESSION_NAME : 'jm_visitor';
if (session_status() !== PHP_SESSION_ACTIVE || session_name() !== $publicSessionName) {
    $finish();
}

$visitorSessionId = $_SESSION['visitor_session_id'] ?? '';
$idVisitor = filter_var($_SESSION['id_visitor'] ?? null, FILTER_VALIDATE_INT);
if (!is_string($visitorSessionId)
    || !preg_match('/\A[a-f0-9]{48}\z/i', $visitorSessionId)
    || $idVisitor === false
    || (int) $idVisitor < 1) {
    $finish();
}

// Rate limit por sessão: impede chamadas excessivas sem depender de IP fornecido pelo cliente.
$now = time();
$windowStart = (int) ($_SESSION['visitor_api_window_start'] ?? 0);
$requestCount = (int) ($_SESSION['visitor_api_request_count'] ?? 0);
if ($windowStart < $now - 60 || $windowStart > $now) {
    $windowStart = $now;
    $requestCount = 0;
}
$requestCount++;
$_SESSION['visitor_api_window_start'] = $windowStart;
$_SESSION['visitor_api_request_count'] = $requestCount;
if ($requestCount > 120) {
    $finish();
}

$event = $data['event'] ?? '';
if (!is_string($event) || !in_array($event, ['pageview', 'scroll_depth', 'exit', 'ping', 'tab_focus'], true)) {
    $finish();
}

// PHP já regista a pageview inicial em includes/visitor.php. Ignorar o evento
// legado do JavaScript evita duplicar cada visita na tabela de pageviews.
if ($event === 'pageview' || $event === 'scroll_depth') {
    $finish();
}

$rawPageUrl = $data['page_url'] ?? '/';
if (!is_string($rawPageUrl) || strlen($rawPageUrl) > 2048) {
    $rawPageUrl = '/';
}
$pageParts = parse_url($rawPageUrl);
if (!is_array($pageParts)) {
    $finish();
}
if (isset($pageParts['host'])) {
    $pageScheme = strtolower((string) ($pageParts['scheme'] ?? ''));
    $pagePort = (int) ($pageParts['port'] ?? ($pageScheme === 'https' ? 443 : 80));
    if (!in_array($pageScheme, ['http', 'https'], true)
        || strtolower((string) $pageParts['host']) !== $expectedHost
        || $pageScheme !== $expectedScheme
        || $pagePort !== $expectedPort) {
        $finish();
    }
}
$pagePath = (string) ($pageParts['path'] ?? '/');
if ($pagePath === '' || !str_starts_with($pagePath, '/') || str_starts_with($pagePath, '//')
    || str_contains($pagePath, '\\') || preg_match('/[\x00-\x1F\x7F]/', $pagePath)) {
    $finish();
}
$pagePath = function_exists('mb_substr') ? mb_substr($pagePath, 0, 500, 'UTF-8') : substr($pagePath, 0, 500);

$timeValue = $data['time_on_page'] ?? 0;
$timeOnPage = (is_int($timeValue) || (is_string($timeValue) && ctype_digit($timeValue)))
    ? max(0, min(86400, (int) $timeValue))
    : 0;

try {
    // O ID só é aceite quando pertence à sessão pública actual e não está bloqueado.
    $owner = $pdo->prepare(
        'SELECT id_visitor, ip_address, country_code, country_name, city, region,
                latitude, longitude, timezone, isp, screen_resolution
         FROM _visitor
         WHERE id_visitor = ? AND session_id = ? AND status_visitor <> \'blocked\'
         LIMIT 1'
    );
    $owner->execute([(int) $idVisitor, $visitorSessionId]);
    $visitor = $owner->fetch(PDO::FETCH_ASSOC);
    if (!$visitor) {
        $finish();
    }

    // Resolução e fuso horário são fornecidos pelo navegador, não pelo User-Agent.
    $screenResolution = '';
    $screenWidth = $data['screen_w'] ?? null;
    $screenHeight = $data['screen_h'] ?? null;
    $validDimension = static function ($value): ?int {
        if (!(is_int($value) || (is_string($value) && ctype_digit($value)))) {
            return null;
        }
        $dimension = (int) $value;
        return ($dimension >= 1 && $dimension <= 20000) ? $dimension : null;
    };
    $screenWidth = $validDimension($screenWidth);
    $screenHeight = $validDimension($screenHeight);
    if ($screenWidth !== null && $screenHeight !== null) {
        $screenResolution = $screenWidth . 'x' . $screenHeight;
    }

    $browserTimezone = '';
    $rawTimezone = $data['timezone'] ?? '';
    if (is_string($rawTimezone)
        && $rawTimezone !== ''
        && strlen($rawTimezone) <= 50
        && preg_match('/^[A-Za-z0-9_+\-]+(?:\/[A-Za-z0-9_+\-]+)*$/', $rawTimezone)) {
        try {
            $browserTimezone = (new DateTimeZone($rawTimezone))->getName();
        } catch (Throwable $error) {
            $browserTimezone = '';
        }
    }

    if (($screenResolution !== '' && $screenResolution !== (string) ($visitor['screen_resolution'] ?? ''))
        || ($browserTimezone !== '' && $browserTimezone !== (string) ($visitor['timezone'] ?? ''))) {
        $metadataUpdate = $pdo->prepare(
            'UPDATE _visitor
             SET screen_resolution = COALESCE(NULLIF(?, \'\'), screen_resolution),
                 timezone = COALESCE(NULLIF(?, \'\'), timezone)
             WHERE id_visitor = ? AND session_id = ? AND status_visitor <> \'blocked\''
        );
        $metadataUpdate->execute([$screenResolution, $browserTimezone, (int) $idVisitor, $visitorSessionId]);
    }

    // O enriquecimento geográfico é opcional: o IP só sai do servidor se a opção
    // VISITOR_GEO_ENABLED estiver activa no ficheiro de ambiente.
    $geoEnabledValue = function_exists('jmbenga_env')
        ? jmbenga_env('VISITOR_GEO_ENABLED', 'false')
        : (getenv('VISITOR_GEO_ENABLED') !== false ? (string) getenv('VISITOR_GEO_ENABLED') : 'false');
    $geoEnabled = filter_var((string) $geoEnabledValue, FILTER_VALIDATE_BOOLEAN);
    $hasGeoData = !empty($visitor['country_code'])
        || !empty($visitor['country_name'])
        || !empty($visitor['city'])
        || !empty($visitor['isp']);

    if ($geoEnabled && !$hasGeoData && empty($_SESSION['visitor_geo_lookup_attempted'])) {
        $_SESSION['visitor_geo_lookup_attempted'] = true;
        $visitorIp = (string) ($visitor['ip_address'] ?? '');
        $geoData = null;

        $publicIpFlags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        $isPublicIp = filter_var($visitorIp, FILTER_VALIDATE_IP, $publicIpFlags) !== false;

        if ($isPublicIp) {
            // Reutilizar dados geográficos de uma sessão anterior com o mesmo IP.
            $geoCache = $pdo->prepare(
                'SELECT country_code, country_name, city, region, latitude, longitude, timezone, isp
                 FROM _visitor
                 WHERE ip_address = ? AND id_visitor <> ?
                   AND (country_code IS NOT NULL OR country_name IS NOT NULL OR city IS NOT NULL OR isp IS NOT NULL)
                 ORDER BY last_seen DESC
                 LIMIT 1'
            );
            $geoCache->execute([$visitorIp, (int) $idVisitor]);
            $cachedGeo = $geoCache->fetch(PDO::FETCH_ASSOC);

            if ($cachedGeo) {
                $geoData = $cachedGeo;
            } else {
                $geoData = jmbenga_visitor_geo_lookup($visitorIp);
            }
        }

        if (is_array($geoData)) {
            $saveGeo = $pdo->prepare(
                'UPDATE _visitor SET
                    country_code = COALESCE(NULLIF(country_code, \'\'), ?),
                    country_name = COALESCE(NULLIF(country_name, \'\'), ?),
                    city = COALESCE(NULLIF(city, \'\'), ?),
                    region = COALESCE(NULLIF(region, \'\'), ?),
                    latitude = COALESCE(latitude, ?),
                    longitude = COALESCE(longitude, ?),
                    timezone = COALESCE(NULLIF(timezone, \'\'), ?),
                    isp = COALESCE(NULLIF(isp, \'\'), ?)
                 WHERE id_visitor = ? AND session_id = ? AND status_visitor <> \'blocked\''
            );
            $saveGeo->execute([
                $geoData['country_code'] ?? null,
                $geoData['country_name'] ?? null,
                $geoData['city'] ?? null,
                $geoData['region'] ?? null,
                $geoData['latitude'] ?? null,
                $geoData['longitude'] ?? null,
                $geoData['timezone'] ?? null,
                $geoData['isp'] ?? null,
                (int) $idVisitor,
                $visitorSessionId,
            ]);
        }
    }

    if ($event === 'exit') {
        $pdo->prepare(
            'UPDATE _visitor
             SET is_online = 0,
                 last_seen = NOW(),
                 page_exit = ?,
                 session_duration = COALESCE(session_duration, 0) + ?
             WHERE id_visitor = ? AND session_id = ? AND status_visitor <> \'blocked\''
        )->execute([$pagePath, $timeOnPage, (int) $idVisitor, $visitorSessionId]);

        if ($timeOnPage > 0) {
            $pdo->prepare(
                'UPDATE _visitor_pageview
                 SET time_on_page = ?
                 WHERE id_visitor = ? AND page_url = ?
                 ORDER BY id_pageview DESC
                 LIMIT 1'
            )->execute([$timeOnPage, (int) $idVisitor, $pagePath]);
        }
    } elseif ($event === 'ping' || $event === 'tab_focus') {
        $pdo->prepare(
            'UPDATE _visitor
             SET is_online = 1, last_seen = NOW()
             WHERE id_visitor = ? AND session_id = ? AND status_visitor <> \'blocked\''
        )->execute([(int) $idVisitor, $visitorSessionId]);
    }
} catch (Throwable $e) {
    // A telemetria nunca deve interromper a navegação pública.
    error_log('[api/visitor] ' . $e->getMessage());
}

$finish();
