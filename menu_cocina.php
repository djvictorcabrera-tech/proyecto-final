<?php
// 1. GESTIÓN DE SESIÓN
require_once 'SessionManager.php';
$session = new SessionManager();

// ---------------- CONEXIÓN A LA BASE DE DATOS ----------------
$host = '127.0.0.1';
$dbname = 'gestor_pedidos';
$username = 'root';
$password = ''; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
// -------------------------------------------------------------

// 2. PROCESAR ACCIÓN DE DESPACHAR PEDIDO, LIBERAR MESA Y REDIRIGIR A FACTURA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'completar_pedido') {
    $idPedidoCompletar = (int) $_POST['id_pedido'];
    
    try {
        // Iniciar transacción para asegurar que ambas actualizaciones se ejecuten correctamente
        $pdo->beginTransaction();

        // 1. Obtener la mesa asociada a este pedido antes de actualizarlo
        $stmtMesa = $pdo->prepare("SELECT id_mesa FROM pedidos WHERE id_pedido = :id_pedido");
        $stmtMesa->execute([':id_pedido' => $idPedidoCompletar]);
        $pedidoData = $stmtMesa->fetch(PDO::FETCH_ASSOC);

        // 2. Cambiar el estado del pedido a 'ENTREGADO'
        $stmtUpdatePedido = $pdo->prepare("UPDATE pedidos SET estado = 'ENTREGADO' WHERE id_pedido = :id_pedido");
        $stmtUpdatePedido->execute([':id_pedido' => $idPedidoCompletar]);

        // 3. Liberar la mesa cambiando su estado a 'DISPONIBLE'
        if ($pedidoData && isset($pedidoData['id_mesa'])) {
            $stmtUpdateMesa = $pdo->prepare("UPDATE mesas SET estado = 'DISPONIBLE' WHERE id_mesa = :id_mesa");
            $stmtUpdateMesa->execute([':id_mesa' => $pedidoData['id_mesa']]);
        }

        // Confirmar los cambios en la base de datos
        $pdo->commit();

        // Redirigir a la factura del pedido entregado
        header("Location: factura.php?id_pedido=" . $idPedidoCompletar);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Error al procesar el despacho: " . $e->getMessage());
    }
}

// 3. CONSULTAR ÚNICAMENTE PEDIDOS ACTIVOS EN COCINA (PENDIENTE / EN_PREPARACION)
$pedidos = [];
try {
    $sql = "SELECT 
                p.id_pedido,
                m.numero_mesa,
                p.estado,
                p.creado_en,
                TIMESTAMPDIFF(MINUTE, p.creado_en, NOW()) AS minutos_transcurridos,
                dp.cantidad,
                dp.notas,
                pb.nombre AS producto
            FROM pedidos p
            INNER JOIN mesas m ON p.id_mesa = m.id_mesa
            INNER JOIN detalle_pedido dp ON p.id_pedido = dp.id_pedido
            INNER JOIN platos_bebidas pb ON dp.id_producto = pb.id_producto
            WHERE p.estado IN ('PENDIENTE', 'EN_PREPARACION')
            ORDER BY p.creado_en ASC";

    $stmt = $pdo->query($sql);
    $rawOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Agrupar los ítems por cada ID de pedido
    foreach ($rawOrders as $row) {
        $id = $row['id_pedido'];
        if (!isset($pedidos[$id])) {
            $pedidos[$id] = [
                'id_pedido' => $id,
                'numero_mesa' => $row['numero_mesa'],
                'estado' => $row['estado'],
                'creado_en' => $row['creado_en'],
                'minutos_transcurridos' => (int) $row['minutos_transcurridos'],
                'items' => []
            ];
        }
        $pedidos[$id]['items'][] = [
            'cantidad' => $row['cantidad'],
            'producto' => $row['producto'],
            'notas' => $row['notas']
        ];
    }
} catch (Exception $e) {
    $error_kds = "Error al obtener comandas: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pantalla de Cocina</title>
    
    <meta http-equiv="refresh" content="15">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="menu_cocina.css">
</head>
<body>

<div class="app-container">
    <header class="app-header">
        <div class="header-title-container">
            <h1 class="app-title">COCINA</h1>
            <div class="header-stats">PEDIDOS ACTIVOS: <?= count($pedidos) ?></div>
        </div>
        <a href="menu.php" class="btn-back">← Volver al Menú</a>
    </header>

    <div class="kds-container">
        <div class="kds-grid">
            <?php if (!empty($error_kds)): ?>
                <div class="empty-kds" style="color: var(--color-urgent);"><?= $error_kds ?></div>
            <?php elseif (empty($pedidos)): ?>
                <div class="empty-kds">No hay pedidos pendientes en cocina.</div>
            <?php else: ?>
                <?php foreach ($pedidos as $pedido): 
                    $minutosTranscurridos = max(0, $pedido['minutos_transcurridos']);

                    $claseUrgencia = 'normal';
                    if ($minutosTranscurridos >= 10) {
                        $claseUrgencia = 'urgent';
                    } elseif ($minutosTranscurridos >= 5) {
                        $claseUrgencia = 'warning';
                    }
                ?>
                    <div class="order-card <?= $claseUrgencia ?>">
                        <div class="order-card-header">
                            <span class="table-name">MESA <?= htmlspecialchars($pedido['numero_mesa']) ?> <small style="font-size:0.8rem; font-weight:normal; opacity:0.8;">(#<?= $pedido['id_pedido'] ?>)</small></span>
                            <span class="time-badge"><?= $minutosTranscurridos ?> MIN</span>
                        </div>

                        <div class="order-card-body">
                            <ul class="items-list">
                                <?php foreach ($pedido['items'] as $item): ?>
                                    <li class="item-row">
                                        <span class="item-qty"><?= $item['cantidad'] ?>x</span>
                                        <?= htmlspecialchars($item['producto']) ?>
                                        <?php if (!empty($item['notas'])): ?>
                                            <span class="item-note"><?= htmlspecialchars($item['notas']) ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="order-card-footer">
                            <form method="POST" action="menu_cocina.php">
                                <input type="hidden" name="action" value="completar_pedido">
                                <input type="hidden" name="id_pedido" value="<?= $pedido['id_pedido'] ?>">
                                <button type="submit" class="btn-complete">Despachar Pedido</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>