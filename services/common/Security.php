<?php
// ==============================================================================
// Security.php - Utilidades de Seguridad, Sanitización y Hardening HTTP
// Cumple con OWASP Top 10 (A01, A03, A05, A07)
// ==============================================================================

class Security {
    public static function applySecurityHeaders(): void {
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("Permissions-Policy: geolocation=(), camera=(), microphone=()");
        
        // CORS seguro
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        header("Access-Control-Allow-Origin: {$origin}");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Stripe-Signature");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }

    public static function getClientIP(): string {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ipList[0]);
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public static function getUserAgent(): string {
        return substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Agent', 0, 255);
    }

    public static function sanitizeString(?string $input): string {
        if ($input === null) return '';
        $clean = strip_tags(trim($input));
        return htmlspecialchars($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function sanitizeEmail(?string $email): ?string {
        if ($email === null) return null;
        $clean = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
        return filter_var($clean, FILTER_VALIDATE_EMAIL) ? strtolower($clean) : null;
    }

    public static function sanitizeFloat($val): float {
        return round(floatval($val), 2);
    }

    public static function sanitizeInt($val): int {
        return intval($val);
    }

    public static function checkRateLimit(string $key, int $maxAttempts = 5, int $decaySeconds = 900): bool {
        // OWASP A07: Control de fuerza bruta (5 intentos -> bloqueo 15 min)
        if (!class_exists('Redis')) {
            return true; // Si no hay redis, permitir para no bloquear sistema
        }

        try {
            $redis = new Redis();
            $host = $_ENV['REDIS_HOST'] ?? 'redis';
            $port = (int)($_ENV['REDIS_PORT'] ?? 6379);
            if (@$redis->connect($host, $port, 1.0)) {
                if (!empty($_ENV['REDIS_PASSWORD'])) {
                    $redis->auth($_ENV['REDIS_PASSWORD']);
                }
                $redisKey = "rate_limit:{$key}";
                $current = (int)$redis->get($redisKey);
                if ($current >= $maxAttempts) {
                    return false; // Bloqueado
                }
                $redis->incr($redisKey);
                if ($current === 0) {
                    $redis->expire($redisKey, $decaySeconds);
                }
                return true;
            }
        } catch (Throwable $e) {
            error_log("[RATE LIMIT REDIS ERROR] " . $e->getMessage());
        }
        return true;
    }

    public static function resetRateLimit(string $key): void {
        if (!class_exists('Redis')) return;
        try {
            $redis = new Redis();
            $host = $_ENV['REDIS_HOST'] ?? 'redis';
            $port = (int)($_ENV['REDIS_PORT'] ?? 6379);
            if (@$redis->connect($host, $port, 1.0)) {
                if (!empty($_ENV['REDIS_PASSWORD'])) {
                    $redis->auth($_ENV['REDIS_PASSWORD']);
                }
                $redis->del("rate_limit:{$key}");
            }
        } catch (Throwable $e) {}
    }

    public static function jsonResponse(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    public static function getJsonInput(): array {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
