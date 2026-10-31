<?php
// ==============================================================================
// InventoryManager.php - Motor Culinario de Inventario, Materias Primas y Recetas
// Lógica de negocio para: Escandallo BOM, Validación previa, Deducción y Kardex
// ==============================================================================

require_once __DIR__ . '/UnitConverter.php';
require_once __DIR__ . '/AuditClient.php';

class InventoryManager {

    /**
     * Estado de un insumo perecedero y qué hacer con él.
     * Vigente: se cocina con normalidad.
     * Por vencer: sigue siendo apto, pero la cocina debe usarlo primero.
     * Vencido: stock utilizable en cero. No se vende ni se cocina; se da de baja como merma.
     */
    public static function evaluarFrescura(array $mp): array {
        $es = (int)($mp['es_perecedero'] ?? 0) === 1;
        $stock = (float)($mp['stock_actual'] ?? 0);
        $fechaRaw = $mp['fecha_vencimiento'] ?? null;
        $alerta = max(0, (int)($mp['dias_alerta'] ?? 3));

        if (!$es) {
            return [
                'es_perecedero' => false,
                'estado_frescura' => 'no_perecedero',
                'dias_para_vencer' => null,
                'fecha_vencimiento' => null,
                'stock_utilizable' => $stock,
                'accion' => null,
            ];
        }

        if ($fechaRaw === null || $fechaRaw === '') {
            return [
                'es_perecedero' => true,
                'estado_frescura' => 'sin_fecha',
                'dias_para_vencer' => null,
                'fecha_vencimiento' => null,
                'stock_utilizable' => $stock,
                'accion' => 'Asignar fecha de vencimiento antes de usarlo en cocina',
            ];
        }

        $fecha = substr((string)$fechaRaw, 0, 10);
        $hoy = new DateTimeImmutable('today');
        $vence = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha) ?: $hoy;
        $dias = (int)$hoy->diff($vence)->format('%r%a');

        if ($dias < 0) {
            return [
                'es_perecedero' => true,
                'estado_frescura' => 'vencido',
                'dias_para_vencer' => $dias,
                'fecha_vencimiento' => $fecha,
                'stock_utilizable' => 0.0,
                'accion' => 'Dar de baja: merma sanitaria. No se cocina ni se vende.',
            ];
        }

        if ($dias <= $alerta) {
            return [
                'es_perecedero' => true,
                'estado_frescura' => 'por_vencer',
                'dias_para_vencer' => $dias,
                'fecha_vencimiento' => $fecha,
                'stock_utilizable' => $stock,
                'accion' => $dias === 0
                    ? 'Usar hoy. Mañana ya no es apto.'
                    : 'Usar primero, antes que el resto de la bodega.',
            ];
        }

        return [
            'es_perecedero' => true,
            'estado_frescura' => 'vigente',
            'dias_para_vencer' => $dias,
            'fecha_vencimiento' => $fecha,
            'stock_utilizable' => $stock,
            'accion' => null,
        ];
    }

    private static function leerInsumosReceta(PDO $catDb, int $productId, bool $detalle): array {
        $columnas = $detalle
            ? "r.id AS receta_id, r.id_materia_prima, r.cantidad_necesaria, r.unidad_medida AS receta_unidad,
               r.es_critico, r.notas,
               mp.codigo AS mp_codigo, mp.nombre AS mp_nombre, mp.stock_actual, mp.stock_minimo,
               mp.unidad_medida AS mp_unidad, mp.costo_unitario, mp.activo AS mp_activo"
            : "r.id_materia_prima, r.cantidad_necesaria, r.unidad_medida AS receta_unidad, r.es_critico,
               mp.nombre AS mp_nombre, mp.codigo AS mp_codigo, mp.stock_actual, mp.unidad_medida AS mp_unidad";
        $frescura = ", mp.es_perecedero, mp.fecha_vencimiento, mp.dias_alerta";
        $from = "
            FROM recetas r
            JOIN materias_primas mp ON r.id_materia_prima = mp.id
            WHERE r.id_producto = :pid AND mp.activo = 1
        ";
        if ($detalle) {
            $from .= " ORDER BY r.es_critico DESC, mp.nombre ASC";
        }

        try {
            $stmt = $catDb->prepare("SELECT {$columnas}{$frescura} {$from}");
            $stmt->execute([':pid' => $productId]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            try {
                $stmt = $catDb->prepare("SELECT {$columnas} {$from}");
                $stmt->execute([':pid' => $productId]);
                return $stmt->fetchAll();
            } catch (Throwable $e2) {
                return [];
            }
        }
    }

    /**
     * Calcula la disponibilidad y porciones máximas preparables de un platillo
     */
    public static function calculateProductAvailability(PDO $catDb, int $productId): array {
        // Consultar el platillo
        $pStmt = $catDb->prepare("SELECT id, nombre, sku, precio, stock, activo FROM productos WHERE id = :id LIMIT 1");
        $pStmt->execute([':id' => $productId]);
        $producto = $pStmt->fetch();

        if (!$producto) {
            return [
                'exists' => false,
                'is_available' => false,
                'available_portions' => 0,
                'has_recipe' => false,
                'bottlenecks' => []
            ];
        }

        $receta = self::leerInsumosReceta($catDb, $productId, true);

        // Caso 1: Platillo sin receta (Artículo directo, ej. bebidas embotelladas)
        if (empty($receta)) {
            $stockActual = (int)$producto['stock'];
            return [
                'exists'             => true,
                'producto_id'        => $productId,
                'nombre'             => $producto['nombre'],
                'has_recipe'         => false,
                'is_available'       => ($stockActual > 0 && (int)$producto['activo'] === 1),
                'available_portions' => max(0, $stockActual),
                'costo_produccion'   => 0.00,
                'bottlenecks'        => ($stockActual <= 0) ? ['Stock directo agotado'] : [],
                'ingredients'        => []
            ];
        }

        // Caso 2: Platillo con receta (Escandallo de materias primas)
        $minPortions = PHP_INT_MAX;
        $bottlenecks = [];
        $costoProduccion = 0.00;
        $ingredientsDetail = [];

        foreach ($receta as $ing) {
            $cantReceta = (float)$ing['cantidad_necesaria'];
            $unidadReceta = $ing['receta_unidad'];
            $unidadMp = $ing['mp_unidad'];
            $frescura = self::evaluarFrescura($ing);
            $stockFisico = (float)$ing['stock_actual'];
            $stockMp = $frescura['stock_utilizable'];
            $esCritico = (int)$ing['es_critico'] === 1;
            $vencido = $frescura['estado_frescura'] === 'vencido';

            // Convertir cantidad requerida a la unidad de medida del inventario
            $cantEnUnidadMp = UnitConverter::convert($cantReceta, $unidadReceta, $unidadMp);
            $costoIngrediente = round($cantEnUnidadMp * (float)$ing['costo_unitario'], 4);
            $costoProduccion += $costoIngrediente;

            $porcionesPosibles = 0;
            if ($cantEnUnidadMp > 0) {
                $porcionesPosibles = (int)floor($stockMp / $cantEnUnidadMp);
                if ($porcionesPosibles < 0) $porcionesPosibles = 0;
            }

            if ($esCritico) {
                if ($porcionesPosibles < $minPortions) {
                    $minPortions = $porcionesPosibles;
                }
                if ($porcionesPosibles <= 0) {
                    $bottlenecks[] = [
                        'materia_prima_id'   => (int)$ing['id_materia_prima'],
                        'codigo'             => $ing['mp_codigo'],
                        'nombre'             => $vencido ? ($ing['mp_nombre'] . ' (vencido)') : $ing['mp_nombre'],
                        'stock_actual'       => $stockFisico,
                        'stock_utilizable'   => $stockMp,
                        'estado_frescura'    => $frescura['estado_frescura'],
                        'stock_formateado'   => UnitConverter::format($vencido ? $stockFisico : $stockMp, $unidadMp),
                        'requerido_porcion'  => $cantEnUnidadMp,
                        'requerido_formateado'=> UnitConverter::format($cantReceta, $unidadReceta),
                        'unidad_medida'      => $unidadMp,
                    ];
                }
            }

            $ingredientsDetail[] = [
                'materia_prima_id'   => (int)$ing['id_materia_prima'],
                'codigo'             => $ing['mp_codigo'],
                'nombre'             => $ing['mp_nombre'],
                'cantidad_receta'    => $cantReceta,
                'unidad_receta'      => $unidadReceta,
                'cantidad_en_unidad_mp' => $cantEnUnidadMp,
                'unidad_mp'          => $unidadMp,
                'stock_actual'       => $stockFisico,
                'stock_utilizable'   => $stockMp,
                'estado_frescura'    => $frescura['estado_frescura'],
                'fecha_vencimiento'  => $frescura['fecha_vencimiento'],
                'porciones_posibles' => $porcionesPosibles,
                'costo_unitario'     => (float)$ing['costo_unitario'],
                'costo_porcion'      => $costoIngrediente,
                'es_critico'         => $esCritico,
                'notas'              => $ing['notas']
            ];
        }

        $availablePortions = ($minPortions === PHP_INT_MAX) ? 0 : max(0, $minPortions);
        $isAvailable = ($availablePortions > 0 && (int)$producto['activo'] === 1);

        return [
            'exists'             => true,
            'producto_id'        => $productId,
            'nombre'             => $producto['nombre'],
            'has_recipe'         => true,
            'is_available'       => $isAvailable,
            'available_portions' => $availablePortions,
            'costo_produccion'   => round($costoProduccion, 2),
            'bottlenecks'        => $bottlenecks,
            'ingredients'        => $ingredientsDetail
        ];
    }

    /**
     * Sincroniza el stock de la tabla `productos` con las porciones reales calculadas
     */
    public static function syncProductStock(PDO $catDb, int $productId): int {
        $avail = self::calculateProductAvailability($catDb, $productId);
        if (!$avail['exists']) return 0;

        $newStock = $avail['available_portions'];

        $upStmt = $catDb->prepare("UPDATE productos SET stock = :stock WHERE id = :id");
        $upStmt->execute([':stock' => $newStock, ':id' => $productId]);

        return $newStock;
    }

    /**
     * Sincroniza el stock de todos los platillos activos
     */
    public static function syncAllProductsStock(PDO $catDb): array {
        $stmt = $catDb->query("SELECT id FROM productos WHERE activo = 1");
        $products = $stmt->fetchAll();
        $synced = [];

        foreach ($products as $p) {
            $pid = (int)$p['id'];
            $st = self::syncProductStock($catDb, $pid);
            $synced[$pid] = $st;
        }

        return $synced;
    }

    /**
     * Valida integralmente si el inventario alcanza para TODOS los platillos de una orden
     * Agrega el consumo total de materias primas compartidas entre varios platillos
     */
    public static function validateOrderInventory(PDO $catDb, array $items): array {
        $mpRequirements = [];
        $directRequirements = [];
        $itemsAnalysis = [];

        foreach ($items as $item) {
            $prodId = (int)($item['id'] ?? $item['id_producto'] ?? 0);
            $cantidad = max(1, (int)($item['cantidad'] ?? 1));

            // Consultar producto
            $pStmt = $catDb->prepare("SELECT id, nombre, sku, precio, stock, activo FROM productos WHERE id = :id LIMIT 1");
            $pStmt->execute([':id' => $prodId]);
            $producto = $pStmt->fetch();

            if (!$producto || (int)$producto['activo'] !== 1) {
                return [
                    'valid' => false,
                    'error' => "El platillo ID {$prodId} no está activo o no existe en el catálogo."
                ];
            }

            $receta = self::leerInsumosReceta($catDb, $prodId, false);

            if (empty($receta)) {
                // Producto sin receta: acumular requerimiento directo
                if (!isset($directRequirements[$prodId])) {
                    $directRequirements[$prodId] = [
                        'producto_id'    => $prodId,
                        'nombre'         => $producto['nombre'],
                        'stock_actual'   => (int)$producto['stock'],
                        'cantidad_total' => 0
                    ];
                }
                $directRequirements[$prodId]['cantidad_total'] += $cantidad;
            } else {
                // Producto con receta: acumular materias primas requeridas
                foreach ($receta as $ing) {
                    $mpId = (int)$ing['id_materia_prima'];
                    $frescura = self::evaluarFrescura($ing);
                    $cantEnUnidadMp = UnitConverter::convert((float)$ing['cantidad_necesaria'], $ing['receta_unidad'], $ing['mp_unidad']);
                    $consumoTotalItem = $cantEnUnidadMp * $cantidad;

                    if (!isset($mpRequirements[$mpId])) {
                        $mpRequirements[$mpId] = [
                            'materia_prima_id'  => $mpId,
                            'codigo'            => $ing['mp_codigo'],
                            'nombre'            => $ing['mp_nombre'],
                            'unidad_medida'     => $ing['mp_unidad'],
                            'stock_actual'      => $frescura['stock_utilizable'],
                            'stock_fisico'      => (float)$ing['stock_actual'],
                            'estado_frescura'   => $frescura['estado_frescura'],
                            'total_requerido'   => 0.0,
                            'platillos'         => []
                        ];
                    }
                    $mpRequirements[$mpId]['total_requerido'] += $consumoTotalItem;
                    $mpRequirements[$mpId]['platillos'][] = [
                        'producto'          => $producto['nombre'],
                        'cantidad_orden'    => $cantidad,
                        'consumo_parcial'   => $consumoTotalItem
                    ];
                }
            }

            $itemsAnalysis[] = [
                'producto_id' => $prodId,
                'nombre'      => $producto['nombre'],
                'cantidad'    => $cantidad,
                'tiene_receta'=> !empty($receta)
            ];
        }

        // 1. Validar materias primas agregadas
        foreach ($mpRequirements as $mpId => $req) {
            $requerido = $req['total_requerido'];
            $disponible = $req['stock_actual'];

            if ($disponible < $requerido) {
                $deficit = round($requerido - $disponible, 3);
                $reqFmt = UnitConverter::format($requerido, $req['unidad_medida']);
                $dispFmt = UnitConverter::format($disponible, $req['unidad_medida']);
                $defFmt = UnitConverter::format($deficit, $req['unidad_medida']);
                $errorStock = "Stock insuficiente de materia prima para completar tu orden. El insumo '{$req['nombre']}' solo cuenta con {$dispFmt} en bodega (la orden requiere {$reqFmt}; faltan {$defFmt}).";
                if (($req['estado_frescura'] ?? '') === 'vencido') {
                    $fisico = UnitConverter::format((float)($req['stock_fisico'] ?? 0), $req['unidad_medida']);
                    $errorStock = "El insumo '{$req['nombre']}' está vencido ({$fisico} en bodega, no aptos). No se puede preparar el pedido: hay que darlo de baja y reponerlo.";
                }

                return [
                    'valid'               => false,
                    'error'               => $errorStock,
                    'insumo_insuficiente' => [
                        'materia_prima_id'=> $mpId,
                        'nombre'          => $req['nombre'],
                        'disponible'      => $dispFmt,
                        'requerido'       => $reqFmt,
                        'deficit'         => $defFmt
                    ]
                ];
            }
        }

        // 2. Validar productos de inventario directo
        foreach ($directRequirements as $prodId => $req) {
            if ($req['stock_actual'] < $req['cantidad_total']) {
                return [
                    'valid'               => false,
                    'error'               => "Porciones insuficientes para '{$req['nombre']}'. Disponibles: {$req['stock_actual']}, solicitados en total: {$req['cantidad_total']}.",
                    'producto_insuficiente'=> $req
                ];
            }
        }

        return [
            'valid'               => true,
            'mp_requirements'     => $mpRequirements,
            'direct_requirements' => $directRequirements,
            'items_analysis'      => $itemsAnalysis
        ];
    }

    /**
     * Descuenta del inventario las materias primas y/o porciones directas de una orden
     * Registra movimientos detallados en el Kardex (movimientos_inventario)
     */
    public static function deductOrderInventory(
        PDO $catDb,
        array $items,
        string $orderNumber,
        int $orderId,
        ?int $userId,
        ?string $userEmail
    ): array {
        // Ejecutar validación completa previa
        $val = self::validateOrderInventory($catDb, $items);
        if (!$val['valid']) {
            throw new Exception($val['error']);
        }

        $movimientosRegistrados = [];
        $productosAfectados = [];

        // 1. Descontar materias primas
        $insMovStmt = $catDb->prepare("
            INSERT INTO movimientos_inventario 
            (id_materia_prima, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, unidad_medida, referencia_tipo, referencia_id, motivo, usuario_id, usuario_email, created_at)
            VALUES 
            (:mp_id, 'consumo_orden', :cant, :ant, :nuevo, :um, 'pedido', :ref_id, :motivo, :uid, :uemail, NOW())
        ");

        $upMpStmt = $catDb->prepare("
            UPDATE materias_primas 
            SET stock_actual = stock_actual - :cant_deduct, updated_at = NOW() 
            WHERE id = :mp_id AND stock_actual >= :cant_min
        ");

        foreach ($val['mp_requirements'] as $mpId => $req) {
            $consumo = $req['total_requerido'];
            $stockAnt = $req['stock_actual'];
            $stockNuevo = max(0.000, round($stockAnt - $consumo, 3));

            $upMpStmt->execute([
                ':cant_deduct' => $consumo,
                ':mp_id'       => $mpId,
                ':cant_min'    => $consumo,
            ]);

            if ($upMpStmt->rowCount() === 0) {
                throw new Exception("Error de concurrencia: el stock del insumo '{$req['nombre']}' varió durante la transacción.");
            }

            $motivo = "Consumo culinario para Pedido #{$orderNumber} (" . count($req['platillos']) . " platillo(s))";
            $insMovStmt->execute([
                ':mp_id'  => $mpId,
                ':cant'   => -$consumo,
                ':ant'    => $stockAnt,
                ':nuevo'  => $stockNuevo,
                ':um'     => $req['unidad_medida'],
                ':ref_id' => $orderNumber,
                ':motivo' => $motivo,
                ':uid'    => $userId,
                ':uemail' => $userEmail
            ]);

            $movimientosRegistrados[] = [
                'materia_prima_id' => $mpId,
                'nombre'           => $req['nombre'],
                'cantidad'         => -$consumo,
                'unidad'           => $req['unidad_medida'],
                'stock_nuevo'      => $stockNuevo
            ];

            // Alerta de stock bajo o agotado en auditoría
            if ($stockNuevo <= 0) {
                AuditClient::log(
                    'INSUMO_AGOTADO',
                    'catalogo-service',
                    $userId,
                    $userEmail,
                    'servicio',
                    $orderId,
                    null,
                    ['materia_prima_id' => $mpId, 'nombre' => $req['nombre'], 'stock_previo' => $stockAnt],
                    ['materia_prima_id' => $mpId, 'nombre' => $req['nombre'], 'stock_actual' => $stockNuevo],
                    'EXITO'
                );
            }
        }

        // 2. Descontar productos directos
        $upDirectStmt = $catDb->prepare("UPDATE productos SET stock = stock - :cant_deduct WHERE id = :id AND stock >= :cant_min");
        foreach ($val['direct_requirements'] as $prodId => $req) {
            $upDirectStmt->execute([
                ':cant_deduct' => $req['cantidad_total'],
                ':id'          => $prodId,
                ':cant_min'    => $req['cantidad_total']
            ]);
            $productosAfectados[] = $prodId;
        }

        // 3. Recalcular y sincronizar porciones disponibles de los platillos ordenados
        foreach ($items as $item) {
            $pid = (int)($item['id'] ?? $item['id_producto'] ?? 0);
            $productosAfectados[] = $pid;
        }
        $productosAfectados = array_unique($productosAfectados);

        foreach ($productosAfectados as $pid) {
            $nuevoStockProd = self::syncProductStock($catDb, $pid);
            if ($nuevoStockProd <= 0) {
                AuditClient::log(
                    'PRODUCTO_AGOTADO',
                    'catalogo-service',
                    $userId,
                    $userEmail,
                    'servicio',
                    $orderId,
                    null,
                    ['producto_id' => $pid],
                    ['producto_id' => $pid, 'stock_actual' => $nuevoStockProd, 'motivo' => 'Insumos agotados tras pedido'],
                    'EXITO'
                );
            }
        }

        return [
            'consumo_materias_primas' => $movimientosRegistrados,
            'productos_sincronizados' => $productosAfectados
        ];
    }

    /**
     * Revierte el inventario consumido en caso de cancelación de pedido
     */
    public static function revertOrderInventory(
        PDO $catDb,
        int $orderId,
        string $orderNumber,
        ?int $userId,
        ?string $userEmail,
        string $motivo = 'Cancelación de pedido en cocina'
    ): array {
        // Buscar movimientos de consumo asociados a este pedido
        $movStmt = $catDb->prepare("
            SELECT m.*, mp.stock_actual, mp.nombre AS mp_nombre
            FROM movimientos_inventario m
            JOIN materias_primas mp ON m.id_materia_prima = mp.id
            WHERE m.referencia_tipo = 'pedido' AND m.referencia_id = :ord AND m.tipo_movimiento = 'consumo_orden'
        ");
        $movStmt->execute([':ord' => $orderNumber]);
        $consumos = $movStmt->fetchAll();

        $revertidos = [];

        $upMpStmt = $catDb->prepare("UPDATE materias_primas SET stock_actual = stock_actual + :cant, updated_at = NOW() WHERE id = :id");
        $insMovStmt = $catDb->prepare("
            INSERT INTO movimientos_inventario 
            (id_materia_prima, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, unidad_medida, referencia_tipo, referencia_id, motivo, usuario_id, usuario_email, created_at)
            VALUES 
            (:mp_id, 'devolucion_orden', :cant, :ant, :nuevo, :um, 'pedido', :ref_id, :motivo, :uid, :uemail, NOW())
        ");

        foreach ($consumos as $c) {
            $cantRevertir = abs((float)$c['cantidad']);
            $stockAnt = (float)$c['stock_actual'];
            $stockNuevo = round($stockAnt + $cantRevertir, 3);
            $mpId = (int)$c['id_materia_prima'];

            $upMpStmt->execute([':cant' => $cantRevertir, ':id' => $mpId]);

            $motivoTexto = "Reintegro a inventario por Pedido #{$orderNumber} cancelado: {$motivo}";
            $insMovStmt->execute([
                ':mp_id'  => $mpId,
                ':cant'   => $cantRevertir,
                ':ant'    => $stockAnt,
                ':nuevo'  => $stockNuevo,
                ':um'     => $c['unidad_medida'],
                ':ref_id' => $orderNumber,
                ':motivo' => $motivoTexto,
                ':uid'    => $userId,
                ':uemail' => $userEmail
            ]);

            $revertidos[] = [
                'materia_prima_id' => $mpId,
                'nombre'           => $c['mp_nombre'],
                'cantidad_reintegrada' => $cantRevertir,
                'stock_actual'     => $stockNuevo
            ];
        }

        // Sincronizar todos los platillos
        self::syncAllProductsStock($catDb);

        AuditClient::log(
            'INVENTARIO_REVERTIDO',
            'pedidos-service',
            $userId,
            $userEmail,
            'operador',
            $orderId,
            null,
            null,
            ['pedido' => $orderNumber, 'motivo' => $motivo, 'insumos_revertidos' => count($revertidos)],
            'EXITO'
        );

        return $revertidos;
    }
}

