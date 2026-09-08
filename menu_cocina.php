<?php
/**
 * ARCHIVO: menu_cocina.php (Kitchen Display System - KDS)
 * PROPÓSITO: Pantalla de comandas activas para la cocina. Muestra tiempos de espera y permite despachar pedidos.
 */

require_once 'SessionManager.php';
require_once 'conexion.php';

$session = new SessionManager();

// 1. DESPACHAR PEDIDO Y REDIRIGIR A FACTURA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'completar_pedido') {
    $idPedidoCompletar = (int) $_POST['id_pedido'];
    
    try {
        // Ejecución de sp_completar_y_despachar_pedido
        $stmt = $conn->prepare("CALL sp_completar_y_despachar_pedido(?)");
        if ($stmt) {
            $stmt->bind_param("i", $idPedidoCompletar);
            $stmt->execute();
            $stmt->close();

            while ($conn->more_results() && $conn->next_result()) {
                if ($result = $conn->store_result()) { $result->free(); }
            }

            // Redirección directa al comprobante de pago
            header("Location: factura.php?id_pedido=" . $idPedidoCompletar);
            exit;
        } else {
            throw new Exception($conn->error);
        }
    } catch (Exception $e) {
        die("Error al procesar el despacho: " . $e->getMessage());
    }
}

// 2. CONSULTAR COMANDAS ACTIVAS EN COCINA
$pedidos = [];
try {
    $result = $conn->query("CALL sp_obtener_pedidos_activos_cocina()");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $id = $row['id_pedido'];
            // Agrupar ítems bajo la misma comanda/pedido
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
        $result->free();
    }

    while ($conn->more_results() && $conn->next_result()) {
        if ($res = $conn->store_result()) {
            $res->free();
        }
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
    
    <!-- Autorrefresco cada 15 segundos para actualización en tiempo real -->
    <meta http-equiv="refresh" content="15">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="menu_cocina.css">
    <link rel="stylesheet" href="normalize.css">
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
                <div class="empty-kds" style="color: var(--color-urgent);"><?= htmlspecialchars($error_kds) ?></div>
            <?php elseif (empty($pedidos)): ?>
                <div class="empty-kds">No hay pedidos pendientes en cocina.</div>
            <?php else: ?>
                <?php foreach ($pedidos as $pedido): 
                    $minutosTranscurridos = max(0, $pedido['minutos_transcurridos']);

                    // Cálculo de nivel de urgencia según el tiempo transcurrido
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