<?php
header('Content-Type: application/json');

// Конфигурация
define('DB_HOST', '');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASS', '');
define('LOG_FILE', __DIR__ . '/bitrix_api.log');
define('BITRIX_OAUTH_URL', 'https://oauth.bitrix24.tech/oauth/token/');

// Include settings file with client credentials
require_once __DIR__ . '/settings.php';

// Функция для логирования
function logError($message, $context = []) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => $message,
        'context' => $context
    ];
    file_put_contents(LOG_FILE, json_encode($logEntry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
}

// Функция для cURL запросов
function makeCurlRequest($url, $method = 'GET', $params = [], $headers = []) {
    $ch = curl_init();
    
    if ($method === 'GET' && !empty($params)) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
    }
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30,
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("cURL error: " . $error);
    }
    
    return [
        'http_code' => $httpCode,
        'response' => $response
    ];
}

// Получаем параметры запроса из POST данных
$input = file_get_contents('php://input');
$requestData = json_decode($input, true) ?: [];

$domain = $requestData['domain'] ?? '';
$method = $requestData['method'] ?? '';
$params = $requestData['params'] ?? [];

// Валидация входных данных
if (empty($domain)) {
    $error = 'Domain parameter is required';
    logError($error, ['request' => $requestData]);
    die(json_encode(['error' => $error]));
}

if (empty($method)) {
    $error = 'Method parameter is required';
    logError($error, ['request' => $requestData]);
    die(json_encode(['error' => $error]));
}

try {
    // Подключаемся к БД
    try {
        $dbh = new PDO(
            "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    } catch (PDOException $e) {
        logError('Database connection error', [
            'error' => $e->getMessage(),
            'domain' => $domain
        ]);
        throw new Exception("Database connection failed");
    }

    // 1. Ищем токен в БД по домену
    try {
        $stmt = $dbh->prepare("
            SELECT `access_token`, `refresh_token`, `token_expires`
            FROM `bitrix_integration_tokens` 
            WHERE `domain` = ? 
            LIMIT 1
        ");
        $stmt->execute([$domain]);
        $tokenData = $stmt->fetch();

        if (!$tokenData) {
            throw new Exception("No tokens found for domain: $domain");
        }
        
        // Добавляем client_id и client_secret из настроек
        $tokenData['client_id'] = defined('C_REST_CLIENT_ID') ? C_REST_CLIENT_ID : '';
        $tokenData['client_secret'] = defined('C_REST_CLIENT_SECRET') ? C_REST_CLIENT_SECRET : '';
        
        if (empty($tokenData['client_id']) || empty($tokenData['client_secret'])) {
            throw new Exception("Client credentials are not configured properly");
        }
    } catch (PDOException $e) {
        logError('Database query error', [
            'error' => $e->getMessage(),
            'domain' => $domain
        ]);
        throw new Exception("Failed to get token data");
    }

    // 2. Проверяем и обновляем access_token при необходимости
    $needTokenRefresh = time() >= ($tokenData['token_expires'] - 300);
    $accessToken = $tokenData['access_token'];

    if ($needTokenRefresh) {
        try {
            logError('Attempting token refresh', [
                'domain' => $domain,
                'current_time' => time(),
                'expires_at' => $tokenData['token_expires']
            ]);
            
            $refreshParams = [
                'grant_type' => 'refresh_token',
                'client_id' => $tokenData['client_id'],
                'client_secret' => $tokenData['client_secret'],
                'refresh_token' => $tokenData['refresh_token']
            ];
            
            $result = makeCurlRequest(BITRIX_OAUTH_URL, 'GET', $refreshParams);
            
            logError('Token refresh response', [
                'domain' => $domain,
                'http_code' => $result['http_code'],
                'response' => $result['response']
            ]);
            
            if ($result['http_code'] != 200) {
                throw new Exception("HTTP code: " . $result['http_code'] . ", Response: " . $result['response']);
            }
            
            $newTokens = json_decode($result['response'], true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON response from token refresh: " . json_last_error_msg());
            }

            if (isset($newTokens['error'])) {
                logError('Token refresh failed', [
                    'error' => $newTokens['error'],
                    'description' => $newTokens['error_description'] ?? null,
                    'domain' => $domain
                ]);
                
                if (in_array($newTokens['error'], ['invalid_grant', 'expired_token'])) {
                    throw new Exception("Refresh token expired. Reinstall the app.");
                }
                
                if ($newTokens['error'] === 'PAYMENT_REQUIRED') {
                    throw new Exception("Payment required for this portal.");
                }
                
                throw new Exception("Token refresh failed: " . ($newTokens['error_description'] ?? $newTokens['error']));
            }

            if (!isset($newTokens['access_token']) || !isset($newTokens['expires_in'])) {
                throw new Exception("Invalid token response: missing required fields");
            }

            // Обновляем данные в БД
            try {
                $updateStmt = $dbh->prepare("
                    UPDATE `bitrix_integration_tokens` 
                    SET `access_token` = ?,
                        `token_expires` = ?,
                        `refresh_token` = ?
                    WHERE `domain` = ?
                ");
                
                $newExpires = time() + (int)$newTokens['expires_in'];
                $newRefreshToken = $newTokens['refresh_token'] ?? $tokenData['refresh_token'];
                
                $updateStmt->execute([
                    $newTokens['access_token'],
                    $newExpires,
                    $newRefreshToken,
                    $domain
                ]);
                
                $accessToken = $newTokens['access_token'];
                
                logError('Token refreshed successfully', [
                    'domain' => $domain,
                    'expires_in' => $newTokens['expires_in'],
                    'new_expires_at' => $newExpires,
                    'refresh_token_updated' => isset($newTokens['refresh_token'])
                ]);
                
            } catch (PDOException $e) {
                logError('Failed to update tokens in database', [
                    'error' => $e->getMessage(),
                    'domain' => $domain
                ]);
                throw new Exception("Failed to save new tokens");
            }
            
        } catch (Exception $e) {
            logError('Token refresh error', [
                'error' => $e->getMessage(),
                'domain' => $domain
            ]);
            throw $e;
        }
    }

    // 3. Выполняем API-запрос к Bitrix24
    try {
        logError('Making API request', [
            'method' => $method,
            'domain' => $domain,
            'params' => $params
        ]);
        
        $apiUrl = "https://$domain/rest/$method.json";
        $apiParams = array_merge($params, ['auth' => $accessToken]);
        
        $result = makeCurlRequest($apiUrl, 'POST', $apiParams);
        
        if ($result['http_code'] != 200) {
            throw new Exception("API returned HTTP code: " . $result['http_code'] . ", Response: " . $result['response']);
        }
        
        $apiResponse = json_decode($result['response'], true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response from API: " . json_last_error_msg());
        }
        
        if (isset($apiResponse['error'])) {
            logError('API request failed', [
                'method' => $method,
                'error' => $apiResponse['error'],
                'description' => $apiResponse['error_description'] ?? null,
                'domain' => $domain
            ]);
            
            if (in_array($apiResponse['error'], ['expired_token', 'invalid_token']) && !$needTokenRefresh) {
                logError('Token seems expired, forcing refresh', ['domain' => $domain]);
            }
            
            throw new Exception("API error: " . ($apiResponse['error_description'] ?? $apiResponse['error']));
        }

        // 4. Возвращаем результат
        echo json_encode([
            'status' => 'success',
            'domain' => $domain,
            'method' => $method,
            'token_refreshed' => $needTokenRefresh,
            'result' => $apiResponse
        ]);

    } catch (Exception $e) {
        logError('API call failed', [
            'method' => $method,
            'error' => $e->getMessage(),
            'domain' => $domain
        ]);
        throw $e;
    }

} catch (PDOException $e) {
    http_response_code(500);
    $error = 'Database error: ' . $e->getMessage();
    logError($error, ['domain' => $domain]);
    echo json_encode([
        'error' => $error,
        'domain' => $domain
    ]);
} catch (Exception $e) {
    http_response_code(400);
    $error = $e->getMessage();
    logError($error, [
        'domain' => $domain,
        'method' => $method
    ]);
    echo json_encode([
        'error' => $error,
        'domain' => $domain,
        'method' => $method,
        'solution' => 'Check application installation and tokens'
    ]);
}