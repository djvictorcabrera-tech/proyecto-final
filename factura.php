<?php
/**
 * ARCHIVO: factura.php
 * PROPÓSITO: Generación y visualización del comprobante de pago/consumo para un pedido específico.
 */

require_once 'SessionManager.php';
require_once 'conexion.php';

$session = new SessionManager();
$usuarioAtendio = $session->getUsuarioNombre() ?: 'Atendido por Usuario';

// Capturar ID de pedido vía GET; si no se suministra, buscar el último pedido registrado
$idPedido = isset($_GET['id_pedido']) ? (int)$_GET['id_pedido'] : 0;

if ($idPedido === 0) {
    // 1. Obtener el último ID de pedido vía sp_obtener_ultimo_id_pedido
    if ($conn->query("CALL sp_obtener_ultimo_id_pedido(@p_id_pedido)")) {
        
        while ($conn->more_results() && $conn->next_result()) {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        }

        // Leer la variable de salida
        $resQuery = $conn->query("SELECT @p_id_pedido AS id_pedido");
        if ($resQuery) {
            $ultimo = $resQuery->fetch_assoc();
            $idPedido = (int) ($ultimo['id_pedido'] ?? 0);
            $resQuery->free();
        }
    }
}

$pedidoHeader = null;
$detalles = [];

if ($idPedido > 0) {
    $idPedidoSafe = (int) $idPedido;

    // 2. Ejecutar sp_obtener_factura_pedido usando multi_query para procesar ambos resultsets
    if ($conn->multi_query("CALL sp_obtener_factura_pedido({$idPedidoSafe})")) {
        
        // RESULTSET 1: Cabecera del pedido (Mesa, total, fecha, estado)
        if ($resHeader = $conn->store_result()) {
            $pedidoHeader = $resHeader->fetch_assoc();
            $resHeader->free(); // Liberar memoria del primer resultSet
        }

        // RESULTSET 2: Detalle/Líneas de la factura (productos, cantidades y precios)
        if ($conn->more_results() && $conn->next_result()) {
            if ($resDetalle = $conn->store_result()) {
                while ($row = $resDetalle->fetch_assoc()) {
                    $detalles[] = $row;
                }
                $resDetalle->free(); // Liberar memoria del segundo resultSet
            }
        }

        // =========================================================================
        // BUCLE DE LIMPIEZA OBLIGATORIO
        // Vacía cualquier resultado residual para evitar el error:
        // "Commands out of sync; you can't run this command now"
        // =========================================================================
        while ($conn->more_results() && $conn->next_result()) {
            if ($extraResult = $conn->store_result()) {
                $extraResult->free();
            }
        }

    } else {
        echo "Error al consultar la factura: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura / Comprobante #<?= htmlspecialchars((string)$idPedido) ?></title>
    <link rel="stylesheet" href="factura.css">
    <link rel="stylesheet" href="normalize.css">
</head>
<body>

<div class="invoice-card">
    <?php if (!$pedidoHeader): ?>
        <div style="text-align: center; color: var(--text-muted);">
            <h3>No se encontró la factura</h3>
            <p style="margin-top: 10px;">El pedido especificado no existe o no contiene productos.</p>
            <a href="menu.php" class="btn btn-back" style="display: inline-block; margin-top: 20px;">Volver al Menú</a>
        </div>
    <?php else: ?>
        <div class="invoice-header">
            <div class="restaurant-name">GESTOR DE PEDIDOS</div>
            <div class="invoice-title">Comprobante de Consumo</div>
        </div>

        <div class="meta-info">
            <div>
                <div><strong>Mesa:</strong> <?= htmlspecialchars($pedidoHeader['numero_mesa']) ?></div>
                <div><strong>Orden:</strong> #<?= htmlspecialchars($pedidoHeader['id_pedido']) ?></div>
                <div><strong>Atendido por:</strong> <?= htmlspecialchars($usuarioAtendio) ?></div>
            </div>
            <div class="text-right">
                <div><strong>Estado:</strong> <?= htmlspecialchars($pedidoHeader['estado']) ?></div>
                <div><?= date('d/m/Y H:i', strtotime($pedidoHeader['creado_en'])) ?></div>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Cant.</th>
                    <th>Producto</th>
                    <th class="text-right">P.Unit</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $item): ?>
                    <tr>
                        <td class="text-center"><strong><?= $item['cantidad'] ?></strong></td>
                        <td><?= htmlspecialchars($item['producto']) ?></td>
                        <td class="text-right">$<?= number_format($item['precio_unitario'], 2) ?></td>
                        <td class="text-right">$<?= number_format($item['subtotal'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="total-section">
            <span class="total-label">Total Pagado:</span>
            <span class="total-amount">$<?= number_format($pedidoHeader['total'], 2) ?></span>
        </div>

        <div class="actions">
            <a href="menu_cocina.php" class="btn btn-cocina">Volver a Cocina</a>
            <a href="menu.php" class="btn btn-back">Nuevo Pedido</a>
            <button onclick="window.print()" class="btn btn-print">Imprimir Factura</button>
        </div>
    <?php endif; ?>
</div>

</body>
</html>