<?php

declare(strict_types=1);

/**
 * Standalone AvalAI reverse proxy.
 *
 * The caller sends the real AvalAI key in Authorization and authenticates to
 * this proxy separately with X-Proxy-Token. The proxy stores no AvalAI key.
 */
const HOP_BY_HOP_HEADERS = [
    'connection',
    'keep-alive',
    'proxy-authenticate',
    'proxy-authorization',
    'te',
    'trailer',
    'transfer-encoding',
    'upgrade',
];

/** @return array<string, mixed> */
function localConfig(): array
{
    $path = __DIR__.'/config.php';
    if (! is_file($path)) {
        return [];
    }

    $config = require $path;

    return is_array($config) ? $config : [];
}

/** @param array<string, mixed> $local */
function configValue(array $local, string $environmentKey, string $localKey, mixed $default = null): mixed
{
    $environmentValue = getenv($environmentKey);
    if ($environmentValue !== false && $environmentValue !== '') {
        return $environmentValue;
    }

    return $local[$localKey] ?? $default;
}

/** @return array<string, string> */
function requestHeaders(): array
{
    $headers = [];
    $source = function_exists('getallheaders') ? getallheaders() : [];

    if (is_array($source)) {
        foreach ($source as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $headers[strtolower($name)] = $value;
            }
        }
    }

    foreach ($_SERVER as $key => $value) {
        if (! is_string($key) || ! is_string($value) || ! str_starts_with($key, 'HTTP_')) {
            continue;
        }

        $name = strtolower(str_replace('_', '-', substr($key, 5)));
        $headers[$name] ??= $value;
    }

    if (! isset($headers['authorization']) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers['authorization'] = (string) $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (! isset($headers['content-type']) && isset($_SERVER['CONTENT_TYPE'])) {
        $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
    }

    return $headers;
}

function jsonError(int $status, string $code, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode([
        'error' => [
            'code' => $code,
            'message' => $message,
            'type' => 'proxy_error',
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function bearerToken(?string $authorization): ?string
{
    if ($authorization === null || preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches) !== 1) {
        return null;
    }

    return trim($matches[1]);
}

function validUpstreamPath(string $path): bool
{
    if (! str_starts_with($path, '/v1/')) {
        return false;
    }

    $decoded = rawurldecode($path);

    return ! str_contains($decoded, "\0")
        && ! str_contains($decoded, '\\')
        && ! preg_match('#(?:^|/)\.\.(?:/|$)#', $decoded);
}

function proxyRoutePath(string $requestUri): string
{
    $path = (string) (parse_url($requestUri, PHP_URL_PATH) ?: '/');
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

    if ($basePath !== '' && $basePath !== '.' && $basePath !== '/'
        && ($path === $basePath || str_starts_with($path, $basePath.'/'))) {
        $path = substr($path, strlen($basePath));
    }

    return $path === '' ? '/' : '/'.ltrim($path, '/');
}

$local = localConfig();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$path = proxyRoutePath($requestUri);

if ($path === '/health' && in_array($method, ['GET', 'HEAD'], true)) {
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if ($method !== 'HEAD') {
        echo '{"ok":true}';
    }
    exit;
}

if (! extension_loaded('curl')) {
    jsonError(500, 'curl_extension_missing', 'The PHP cURL extension is required.');
}

if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    header('Allow: GET, POST, PUT, PATCH, DELETE');
    jsonError(405, 'method_not_allowed', 'The HTTP method is not supported by this proxy.');
}

if (! validUpstreamPath($path)) {
    jsonError(404, 'route_not_found', 'Only AvalAI /v1/* endpoints are available.');
}

$proxyAccessToken = trim((string) configValue($local, 'PROXY_ACCESS_TOKEN', 'proxy_access_token'));
$upstreamBaseUrl = rtrim((string) configValue(
    $local,
    'AVALAI_UPSTREAM_BASE_URL',
    'avalai_upstream_base_url',
    'https://api.avalai.ir',
), '/');
$connectTimeout = max(1, (int) configValue($local, 'PROXY_CONNECT_TIMEOUT', 'connect_timeout_seconds', 15));
$requestTimeout = max(1, (int) configValue($local, 'PROXY_REQUEST_TIMEOUT', 'request_timeout_seconds', 180));
$maxRequestBytes = max(1024, (int) configValue($local, 'PROXY_MAX_REQUEST_BYTES', 'max_request_bytes', 20 * 1024 * 1024));

if ($proxyAccessToken === '') {
    jsonError(500, 'proxy_not_configured', 'The proxy access token is not configured.');
}

$headers = requestHeaders();
$providedProxyToken = trim((string) ($headers['x-proxy-token'] ?? ''));
if ($providedProxyToken === '' || ! hash_equals($proxyAccessToken, $providedProxyToken)) {
    jsonError(401, 'unauthorized', 'A valid X-Proxy-Token header is required.');
}

$providedAvalaiToken = bearerToken($headers['authorization'] ?? null);
if ($providedAvalaiToken === null || $providedAvalaiToken === '') {
    header('WWW-Authenticate: Bearer realm="avalai"');
    jsonError(401, 'avalai_key_missing', 'The AvalAI bearer token is required.');
}

$declaredLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
if ($declaredLength > $maxRequestBytes) {
    jsonError(413, 'request_too_large', 'The request body exceeds the configured limit.');
}

$body = file_get_contents('php://input', false, null, 0, $maxRequestBytes + 1);
if ($body === false) {
    jsonError(400, 'request_body_unreadable', 'The request body could not be read.');
}
if (strlen($body) > $maxRequestBytes) {
    jsonError(413, 'request_too_large', 'The request body exceeds the configured limit.');
}

$query = (string) (parse_url($requestUri, PHP_URL_QUERY) ?? '');
$upstreamUrl = $upstreamBaseUrl.$path.($query !== '' ? '?'.$query : '');
$forwardHeaders = [
    'Authorization: Bearer '.$providedAvalaiToken,
    'Expect:',
];

foreach ($headers as $name => $value) {
    if ($name === 'authorization' || $name === 'x-proxy-token' || $name === 'host' || $name === 'content-length' || in_array($name, HOP_BY_HOP_HEADERS, true)) {
        continue;
    }

    // These are end-to-end request headers used by OpenAI-compatible APIs.
    if ($name === 'accept' || $name === 'content-type' || $name === 'user-agent'
        || $name === 'idempotency-key' || str_starts_with($name, 'openai-')) {
        $forwardHeaders[] = $name.': '.$value;
    }
}

if (! isset($headers['accept'])) {
    $forwardHeaders[] = 'Accept: application/json';
}
if ($body !== '' && ! isset($headers['content-type'])) {
    $forwardHeaders[] = 'Content-Type: application/json';
}

$responseStarted = false;
$responseBytes = 0;
$responseStatus = 502;
$responseHeaders = [];

$curl = curl_init($upstreamUrl);
if ($curl === false) {
    jsonError(500, 'curl_initialization_failed', 'The upstream request could not be initialized.');
}

curl_setopt_array($curl, [
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => $forwardHeaders,
    CURLOPT_CONNECTTIMEOUT => $connectTimeout,
    CURLOPT_TIMEOUT => $requestTimeout,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_MAXREDIRS => 0,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
    CURLOPT_NOSIGNAL => true,
    CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseStatus, &$responseHeaders, &$responseStarted): int {
        $length = strlen($line);
        $trimmed = trim($line);

        if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $trimmed, $matches) === 1) {
            $responseStatus = (int) $matches[1];
            $responseHeaders = [];

            return $length;
        }

        if ($trimmed === '') {
            if ($responseStatus >= 200 && ! $responseStarted) {
                http_response_code($responseStatus);
                foreach ($responseHeaders as [$name, $value]) {
                    header($name.': '.$value, false);
                }
                header('X-Accel-Buffering: no');
                $responseStarted = true;
            }

            return $length;
        }

        if (! str_contains($line, ':')) {
            return $length;
        }

        [$name, $value] = array_map('trim', explode(':', $line, 2));
        $normalized = strtolower($name);
        if ($name !== '' && ! in_array($normalized, HOP_BY_HOP_HEADERS, true) && $normalized !== 'content-length') {
            $responseHeaders[] = [$name, $value];
        }

        return $length;
    },
    CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$responseBytes): int {
        $length = strlen($chunk);
        $responseBytes += $length;
        echo $chunk;
        flush();

        return $length;
    },
]);

if ($body !== '' || in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
    curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
}

$success = curl_exec($curl);
$curlErrorNumber = curl_errno($curl);
curl_close($curl);

if ($success === false && $responseBytes === 0) {
    if (! $responseStarted) {
        jsonError(502, 'upstream_unavailable', 'AvalAI upstream request failed (cURL '.$curlErrorNumber.').');
    }

    exit;
}

// The upstream status, headers and raw response bytes have already been relayed.
exit;
