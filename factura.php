<?php
require_once 'SessionManager.php';
$session = new SessionManager();

$host = '127.0.0.1';
$dbname = 'gestor_pedidos';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener el ID del pedido vía GET (si no se especifica, toma el último registrado)
$idPedido = isset($_GET['id_pedido']) ? (int)$_GET['id_pedido'] : 0;

if ($idPedido === 0) {
    $stmtUltimo = $pdo->query("SELECT id_pedido FROM pedidos ORDER BY id_pedido DESC LIMIT 1");
    $ultimo = $stmtUltimo->fetch(PDO::FETCH_ASSOC);
    $idPedido = $ultimo['id_pedido'] ?? 0;
}

$pedidoHeader = null;
$detalles = [];

if ($idPedido > 0) {
    // 1. Obtener encabezado del pedido
    $stmtHeader = $pdo->prepare("
        SELECT p.id_pedido, p.estado, p.total, p.creado_en, m.numero_mesa
        FROM pedidos p
        INNER JOIN mesas m ON p.id_mesa = m.id_mesa
        WHERE p.id_pedido = :id_pedido
    ");
    $stmtHeader->execute([':id_pedido' => $idPedido]);
    $pedidoHeader = $stmtHeader->fetch(PDO::FETCH_ASSOC);

    // 2. Obtener desglose de detalle_pedido
    $stmtDetalle = $pdo->prepare("
        SELECT dp.cantidad, dp.precio_unitario, dp.subtotal, dp.notas, pb.nombre AS producto
        FROM detalle_pedido dp
        INNER JOIN platos_bebidas pb ON dp.id_producto = pb.id_producto
        WHERE dp.id_pedido = :id_pedido
    ");
    $stmtDetalle->execute([':id_pedido' => $idPedido]);
    $detalles = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura / Comprobante #<?= htmlspecialchars($idPedido) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #0b111e;
            --card-bg: #151d2a;
            --text-main: #ffffff;
            --text-muted: #8e9bb0;
            --primary: #3b82f6;
            --border: #232f45;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .invoice-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            width: 100%;
            max-width: 450px;
            padding: 32px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
        }

        .invoice-header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px dashed var(--border);
        }

        .restaurant-name { font-size: 1.4rem; font-weight: 800; color: var(--primary); letter-spacing: 1px; }
        .invoice-title { font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; margin-top: 4px; }

        .meta-info {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
            font-size: 0.88rem;
            color: var(--text-muted);
        }

        .meta-info strong { color: var(--text-main); }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .items-table th {
            text-align: left;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--text-muted);
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
        }

        .items-table td {
            padding: 10px 0;
            font-size: 0.9rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .total-section {
            border-top: 2px dashed var(--border);
            padding-top: 16px;
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .total-label { font-size: 1.1rem; font-weight: 700; }
        .total-amount { font-size: 1.5rem; font-weight: 800; color: var(--primary); }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 28px;
        }

        .btn {
            flex: 1;
            padding: 12px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.85rem;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: opacity 0.2s;
        }

        .btn-print { background-color: var(--primary); color: #fff; }
        .btn-back { background-color: #232f45; color: var(--text-muted); }
        .btn:hover { opacity: 0.9; }

        @media print {
            body { background: #fff; color: #000; }
            .invoice-card { border: none; box-shadow: none; width: 100%; max-width: 100%; padding: 0; }
            .actions { display: none; }
            .restaurant-name, .total-amount { color: #000; }
            .items-table th, .items-table td { border-color: #ddd; }
        }
            .btn-cocina { 
                background-color: #10b981; /* Verde estilo cocina */
                color: #ffffff; 
                }
                .btn-cocina:hover { 
                    background-color: #059669; 
                }
    </style>
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