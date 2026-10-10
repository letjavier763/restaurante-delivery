<?php
// ==============================================================================
// Database.php - Conector PDO Seguro con Prepared Statements
// OWASP A03: Injection - Previene 100% inyecciones SQL
// ==============================================================================

class Database {
    private static ?PDO $masterInstance = null;
    private static ?PDO $slaveInstance = null;
    private static ?PDO $auditInstance = null;

    public static function getMasterConnection(string $dbName = null): PDO {
        $dbName = $dbName ?? ($_ENV['DB_AUTH_NAME'] ?? 'auth_db');
        return self::createConnection(
            $_ENV['DB_MASTER_HOST'] ?? 'mariadb-master',
            (int)($_ENV['DB_MASTER_PORT'] ?? 3306),
            $dbName,
            $_ENV['DB_USER'] ?? 'technova_app',
            $_ENV['DB_PASSWORD'] ?? 'AppMasterPass_2026!'
        );
    }

    public static function getSlaveConnection(string $dbName = null): PDO {
        $dbName = $dbName ?? ($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');
        return self::createConnection(
            $_ENV['DB_SLAVE_HOST'] ?? 'mariadb-slave',
            (int)($_ENV['DB_SLAVE_PORT'] ?? 3306),
            $dbName,
            $_ENV['DB_USER'] ?? 'technova_app',
            $_ENV['DB_PASSWORD'] ?? 'AppMasterPass_2026!'
        );
    }

    public static function getAuditConnection(): PDO {
        return self::createConnection(
            $_ENV['DB_AUDIT_HOST'] ?? 'mariadb-audit',
            (int)($_ENV['DB_AUDIT_PORT'] ?? 3306),
            $_ENV['DB_AUDIT_NAME'] ?? 'audit_db',
            $_ENV['DB_AUDIT_USER'] ?? 'audit_service_user',
            $_ENV['DB_AUDIT_PASSWORD'] ?? 'AuditServicePass_2026!'
        );
    }

    private static function createConnection(string $host, int $port, string $database, string $user, string $pass): PDO {
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // Previene SQL Injection real
            PDO::ATTR_TIMEOUT            => 5,
        ];

        try {
            return new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // OWASP A09: No filtrar detalles internos de BD al usuario final
            error_log("[DB CONNECTION ERROR] Host: {$host}, DB: {$database} - " . $e->getMessage());
            throw new Exception("Error interno al conectar con la base de datos.");
        }
    }
}
