<?php
// ==============================================================================
// JWT.php - Implementación Robusta de JSON Web Token (HS256)
// Cumple con Zero Trust & OWASP A07 (Autenticación y Revocación en Redis)
// ==============================================================================

class JWT {
    private static function getSecret(): string {
        return $_ENV['JWT_SECRET'] ?? 'c8f8b5f939e08328dc4391ef417f7d1b33e5ca8db954203d9803bf5fcf216a75';
    }

    public static function encode(array $payload, int $ttl = 3600): string {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];

        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + $ttl;
        $payload['iss'] = $_ENV['JWT_ISSUER'] ?? 'technova-auth-service';
        if (!isset($payload['jti'])) {
            $payload['jti'] = bin2hex(random_bytes(16));
        }

        $base64UrlHeader = self::base64UrlEncode(json_encode($header));
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', "{$base64UrlHeader}.{$base64UrlPayload}", self::getSecret(), true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return "{$base64UrlHeader}.{$base64UrlPayload}.{$base64UrlSignature}";
    }

    public static function decode(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $sigB64] = $parts;

        $header = json_decode(self::base64UrlDecode($headerB64), true);
        $payload = json_decode(self::base64UrlDecode($payloadB64), true);

        if (!$header || !$payload) {
            return null;
        }

        if (($header['alg'] ?? '') !== 'HS256') {
            return null;
        }

        // Validación de firma constante en tiempo (previene timing attacks)
        $expectedSig = hash_hmac('sha256', "{$headerB64}.{$payloadB64}", self::getSecret(), true);
        $actualSig = self::base64UrlDecode($sigB64);

        if (!hash_equals($expectedSig, $actualSig)) {
            return null;
        }

        // Validación de expiración
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        // Validación contra lista negra en Redis (Token Revocado / Logout)
        if (isset($payload['jti']) && self::isBlacklisted($payload['jti'])) {
            return null;
        }

        return $payload;
    }

    public static function revoke(string $jti, int $ttl = 3600): bool {
        try {
            $redis = self::getRedisConnection();
            if ($redis) {
                $redis->setex("jwt_blacklist:{$jti}", $ttl, 'revoked');
                return true;
            }
        } catch (Throwable $e) {
            error_log("[REDIS BLACKLIST ERROR] " . $e->getMessage());
        }
        return false;
    }

    public static function isBlacklisted(string $jti): bool {
        try {
            $redis = self::getRedisConnection();
            if ($redis && $redis->exists("jwt_blacklist:{$jti}")) {
                return true;
            }
        } catch (Throwable $e) {
            error_log("[REDIS CHECK ERROR] " . $e->getMessage());
        }
        return false;
    }

    public static function getBearerToken(): ?string {
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $reqHeaders = apache_request_headers();
            if (isset($reqHeaders['Authorization'])) {
                $headers = trim($reqHeaders['Authorization']);
            }
        }

        if (!empty($headers) && preg_match('/Bearer\s+(\S+)/i', $headers, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private static function getRedisConnection(): ?Redis {
        static $redis = null;
        if ($redis !== null) {
            return $redis;
        }

        if (!class_exists('Redis')) {
            return null;
        }

        try {
            $r = new Redis();
            $host = $_ENV['REDIS_HOST'] ?? 'redis';
            $port = (int)($_ENV['REDIS_PORT'] ?? 6379);
            if (@$r->connect($host, $port, 2.0)) {
                if (!empty($_ENV['REDIS_PASSWORD'])) {
                    $r->auth($_ENV['REDIS_PASSWORD']);
                }
                $redis = $r;
                return $redis;
            }
        } catch (Throwable $e) {
            // Redis fallback
        }
        return null;
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
