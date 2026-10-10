<?php
// ==============================================================================
// AuditClient.php - Emisor de Eventos para security-audit-service
// Trazabilidad Total del Ciclo de Compra y Seguridad (Criterio 11 & SEC-2)
// ==============================================================================

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Security.php';

class AuditClient {
    public static function log(
        string $accion,
        string $servicio,
        ?int $usuarioId = null,
        ?string $usuarioEmail = null,
        string $rol = 'anonimo',
        ?int $pedidoId = null,
        ?int $pagoId = null,
        $datoAnterior = null,
        $datoNuevo = null,
        string $resultado = 'EXITO',
        ?string $ip = null,
        ?string $userAgent = null
    ): bool {
        $ip = $ip ?? Security::getClientIP();
        $userAgent = $userAgent ?? Security::getUserAgent();
        $timestamp = date('Y-m-d H:i:s') . '.' . sprintf('%03d', (int)(microtime(true) * 1000) % 1000);

        $payload = [
            'timestamp'      => $timestamp,
            'usuario_id'     => $usuarioId,
            'usuario_email'  => $usuarioEmail,
            'rol'            => $rol,
            'accion'         => $accion,
            'servicio'       => $servicio,
            'pedido_id'      => $pedidoId,
            'pago_id'        => $pagoId,
            'dato_anterior'  => $datoAnterior !== null ? json_encode($datoAnterior, JSON_UNESCAPED_UNICODE) : null,
            'dato_nuevo'     => $datoNuevo !== null ? json_encode($datoNuevo, JSON_UNESCAPED_UNICODE) : null,
            'ip_origen'      => $ip,
            'user_agent'     => $userAgent,
            'resultado'      => $resultado,
        ];

        // Intentar inserción directa en audit_db (Append-Only)
        try {
            $pdo = Database::getAuditConnection();
            $stmt = $pdo->prepare("
                INSERT INTO `security_audit_logs`
                (`timestamp`, `usuario_id`, `usuario_email`, `rol`, `accion`, `servicio`, `pedido_id`, `pago_id`, `dato_anterior`, `dato_nuevo`, `ip_origen`, `user_agent`, `resultado`)
                VALUES
                (:timestamp, :usuario_id, :usuario_email, :rol, :accion, :servicio, :pedido_id, :pago_id, :dato_anterior, :dato_nuevo, :ip_origen, :user_agent, :resultado)
            ");
            return $stmt->execute($payload);
        } catch (Throwable $e) {
            error_log("[AUDIT LOG ERROR] Fallo al registrar evento {$accion}: " . $e->getMessage());
            return false;
        }
    }
}
