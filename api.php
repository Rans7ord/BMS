<?php
/**
 * Logonvoice BMS — mNotify API Proxy
 * ─────────────────────────────────────
 * No logging, no data storage. Pure pass-through proxy.
 *
 * Drop this in the same folder as index.html.
 * Set your mNotify API key in .env or directly in $apiKey below.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── API Key ──────────────────────────────────────────────────────
// Option A: Load from .env file (recommended)
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        [$key, $val] = array_map('trim', explode('=', $line, 2));
        $_ENV[$key] = $val;
    }
}
$apiKey  = $_ENV['MNOTIFY_API_KEY'] ?? 'YOUR_API_KEY_HERE';
$baseUrl = 'https://api.mnotify.com/api';

// ── Router ───────────────────────────────────────────────────────
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'balance':
        echo json_encode(getSmsBalance($baseUrl, $apiKey));
        break;

    case 'register':
        $body = getRequestBody();
        if (empty($body['sender_name']) || empty($body['purpose'])) {
            echo json_encode(['status' => 'error', 'message' => 'sender_name and purpose are required']);
            break;
        }
        echo json_encode(registerSenderId($baseUrl, $apiKey, $body['sender_name'], $body['purpose']));
        break;

    case 'status':
        $body = getRequestBody();
        if (empty($body['sender_name'])) {
            echo json_encode(['status' => 'error', 'message' => 'sender_name is required']);
            break;
        }
        echo json_encode(checkSenderStatus($baseUrl, $apiKey, $body['sender_name']));
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
}

// ── Helpers ──────────────────────────────────────────────────────

function getRequestBody(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

function makeRequest(string $method, string $url, array $data = []): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);

    if (!empty($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['status' => 'error', 'message' => 'cURL error: ' . $curlErr];
    }

    $decoded = json_decode($response, true);
    return $decoded ?? ['status' => 'error', 'message' => 'Invalid JSON response', 'raw' => $response];
}

// ─── 1. Check SMS Balance ─────────────────────────────────────────
function getSmsBalance(string $base, string $key): array {
    $url = $base . '/balance/sms?key=' . urlencode($key);
    return makeRequest('GET', $url);
}

// ─── 2. Register Sender ID ────────────────────────────────────────
function registerSenderId(string $base, string $key, string $senderName, string $purpose): array {
    $url = $base . '/senderid/register?key=' . urlencode($key);
    return makeRequest('POST', $url, [
        'sender_name' => $senderName,
        'purpose'     => $purpose,
    ]);
}

// ─── 3. Check Sender ID Status ───────────────────────────────────
function checkSenderStatus(string $base, string $key, string $senderName): array {
    $url = $base . '/senderid/status?key=' . urlencode($key);
    return makeRequest('POST', $url, [
        'sender_name' => $senderName,
    ]);
}
