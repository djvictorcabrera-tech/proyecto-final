<?php
session_start();

// Cargar comanda estática si la sesión está vacía
if (!isset($_SESSION['comandas_cocina'])) {
    $_SESSION['comandas_cocina'] = [];
}

// Acción de responder a AJAX para marcar como LISTO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (isset($data['action']) && $data['action'] === 'marcar_listo' && isset($data['id'])) {
        $orderId = $data['id'];
        $_SESSION['comandas_cocina'] = array_filter($_SESSION['comandas_cocina'], function($item) use ($orderId) {
            return $item['id'] != $orderId;
        });
        $_SESSION['comandas_cocina'] = array_values($_SESSION['comandas_cocina']);

        echo json_encode(['status' => 'success', 'remaining' => count($_SESSION['comandas_cocina'])]);
        exit;
    }
}

$comandas = $_SESSION['comandas_cocina'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cocina</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="menu_cocina.css">
</head>
<body>

<div class="app-container">
    <!-- Header Bar -->
    <header class="app-header">
        <div class="header-title-container">
            <h1 class="app-title">Cocina</h1>
            <div class="header-stats">Pedidos Activos: <span id="active-count"><?php echo count($comandas); ?></span></div>
        </div>
        <a href="menu.php" class="btn-back">← Volver al Menú</a>
    </header>

    <!-- KDS Display -->
    <main class="kds-container">
        <div class="kds-grid" id="kds-grid">
            <?php if (empty($comandas)): ?>
                <div class="empty-kds">
                    ✨ No hay pedidos pendientes en la cocina.
                </div>
            <?php else: ?>
                <?php foreach ($comandas as $c): 
                    // Cálculo de minutos transcurridos
                    $minutos = floor((time() - strtotime($c['tiempo'])) / 60);
                    if ($minutos < 0) $minutos = 0;

                    $statusClass = 'normal';
                    if ($minutos >= 10) {
                        $statusClass = 'urgent';
                    } elseif ($minutos >= 5) {
                        $statusClass = 'warning';
                    }
                ?>
                <div class="order-card <?php echo $statusClass; ?>" id="order-card-<?php echo $c['id']; ?>">
                    <div>
                        <div class="order-card-header">
                            <span class="table-name"><?php echo htmlspecialchars($c['mesa']); ?></span>
                            <span class="time-badge" data-timestamp="<?php echo strtotime($c['tiempo']); ?>">
                                Hace <?php echo $minutos; ?> min
                            </span>
                        </div>
                        <div class="order-card-body">
                            <ul class="items-list">
                                <?php foreach ($c['items'] as $item): ?>
                                    <li class="item-row">
                                        • <span class="item-qty"><?php echo $item['qty']; ?>x</span> 
                                        <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                        <?php if (!empty($item['note'])): ?>
                                            <span class="item-note">- <?php echo htmlspecialchars($item['note']); ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="order-card-footer">
                        <button class="btn-complete" onclick="marcarListo(<?php echo $c['id']; ?>)">Marcar Listo</button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
    // Marcar orden como lista vía AJAX
    async function marcarListo(orderId) {
        const card = document.getElementById(`order-card-${orderId}`);
        if (card) {
            card.style.opacity = '0';
            card.style.transform = 'scale(0.9)';
        }

        try {
            const response = await fetch('cocina.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'marcar_listo', id: orderId })
            });

            const result = await response.json();
            if (result.status === 'success') {
                setTimeout(() => {
                    if (card) card.remove();
                    document.getElementById('active-count').textContent = result.remaining;

                    if (result.remaining === 0) {
                        document.getElementById('kds-grid').innerHTML = `
                            <div class="empty-kds">✨ No hay comandas pendientes en cocina.</div>
                        `;
                    }
                }, 300);
            }
        } catch (error) {
            console.error('Error al actualizar el estado de la comanda:', error);
            if (card) {
                card.style.opacity = '1';
                card.style.transform = 'none';
            }
        }
    }

    // Actualización de tiempos en tiempo real
    setInterval(() => {
        const badges = document.querySelectorAll('.time-badge[data-timestamp]');
        const now = Math.floor(Date.now() / 1000);

        badges.forEach(badge => {
            const timestamp = parseInt(badge.getAttribute('data-timestamp'));
            const elapsedMinutes = Math.floor((now - timestamp) / 60);
            badge.textContent = `Hace ${elapsedMinutes} min`;

            const card = badge.closest('.order-card');
            if (elapsedMinutes >= 10) {
                card.className = 'order-card urgent';
            } else if (elapsedMinutes >= 5) {
                card.className = 'order-card warning';
            } else {
                card.className = 'order-card normal';
            }
        });
    }, 30000); // Actualiza cada 30 segundos
</script>

</body>
</html>