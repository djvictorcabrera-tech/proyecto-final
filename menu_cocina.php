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
    <title>Control KDS de Cocina (Comandas Activas)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-main: #0b111e;
            --bg-card: #151d2a;
            --bg-card-hover: #1c2638;
            --bg-sidebar: #0f172a;
            --text-primary: #ffffff;
            --text-muted: #8e9bb0;
            
            /* TEMA AZUL PRINCIPAL */
            --color-primary: #3b82f6;
            --color-primary-hover: #2563eb;
            
            /* CODIFICACIÓN DE TIEMPO / ESTADOS */
            --color-urgent: #ef4444;       /* Rojo: > 10 min */
            --color-warning: #f59e0b;      /* Naranja: 5-10 min */
            --color-normal: #10b981;       /* Verde/Azul: < 5 min */

            --border-color: #232f45;
            --radius-lg: 12px;
            --radius-md: 8px;
            --radius-sm: 6px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', system-ui, sans-serif; }

        html, body {
            width: 100vw; height: 100vh;
            background-color: var(--bg-main);
            color: var(--text-primary);
            overflow: hidden;
        }

        .app-container {
            width: 100vw; height: 100vh;
            display: flex; flex-direction: column;
            background: #111827;
        }

        /* Header Bar */
        .app-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 16px 32px; background-color: var(--bg-sidebar);
            border-bottom: 1px solid var(--border-color); flex-shrink: 0;
        }

        .header-title-container { display: flex; align-items: center; gap: 16px; }

        .app-title {
            color: var(--color-primary); font-size: 1.25rem;
            font-weight: 800; letter-spacing: 0.8px; text-transform: uppercase;
        }

        .header-stats {
            background-color: var(--border-color); color: #e2e8f0;
            font-weight: 700; font-size: 0.9rem; padding: 8px 16px;
            border-radius: var(--radius-sm); letter-spacing: 0.5px;
        }

        .btn-back {
            background-color: var(--bg-card); color: var(--text-muted);
            border: 1px solid var(--border-color); padding: 8px 16px;
            border-radius: var(--radius-sm); font-size: 0.85rem; font-weight: 700;
            text-decoration: none; transition: all 0.2s ease;
        }
        .btn-back:hover { color: #ffffff; background-color: var(--color-primary); border-color: var(--color-primary); }

        /* KDS Grid Container */
        .kds-container {
            flex-grow: 1; padding: 32px;
            overflow-y: auto; height: calc(100vh - 65px);
        }

        .kds-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
            gap: 24px; align-items: start;
        }

        /* Comanda Card */
        .order-card {
            background-color: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--color-normal);
            display: flex; flex-direction: column;
            justify-content: space-between;
            min-height: 380px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            transition: transform 0.2s ease, opacity 0.3s ease;
            position: relative; overflow: hidden;
        }

        /* Variaciones según tiempo */
        .order-card.urgent { border-color: var(--color-urgent); }
        .order-card.warning { border-color: var(--color-warning); }
        .order-card.normal { border-color: var(--color-primary); }

        .order-card-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 16px 20px; background-color: rgba(15, 23, 42, 0.6);
            border-bottom: 1px solid var(--border-color);
        }

        .table-name { font-size: 1.15rem; font-weight: 800; color: #ffffff; }

        .time-badge {
            font-size: 0.78rem; font-weight: 800; padding: 4px 10px;
            border-radius: 20px; color: #ffffff; text-transform: uppercase;
        }
        .urgent .time-badge { background-color: var(--color-urgent); }
        .warning .time-badge { background-color: var(--color-warning); color: #000; }
        .normal .time-badge { background-color: var(--color-primary); }

        /* Details & List */
        .order-card-body { padding: 20px; flex-grow: 1; }

        .items-list { list-style: none; display: flex; flex-direction: column; gap: 14px; }

        .item-row { font-size: 0.92rem; color: #f1f5f9; line-height: 1.4; }

        .item-qty { font-weight: 800; color: var(--color-primary); margin-right: 4px; }
        .urgent .item-qty { color: var(--color-urgent); }

        .item-note {
            display: block; font-size: 0.78rem; color: var(--text-muted);
            margin-top: 2px; padding-left: 16px; font-style: italic;
        }

        /* Card Footer & Action Button */
        .order-card-footer { padding: 16px 20px; border-top: 1px solid var(--border-color); }

        .btn-complete {
            width: 100%; background-color: var(--color-primary);
            color: #ffffff; border: none; padding: 12px;
            border-radius: var(--radius-md); font-weight: 800;
            font-size: 0.85rem; letter-spacing: 0.8px; text-transform: uppercase;
            cursor: pointer; transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
        }

        .btn-complete:hover {
            background-color: var(--color-primary-hover);
            box-shadow: 0 6px 18px rgba(59, 130, 246, 0.4);
            transform: translateY(-1px);
        }

        .empty-kds {
            grid-column: 1 / -1; text-align: center;
            padding: 80px 20px; color: var(--text-muted); font-size: 1.1rem;
        }
    </style>
</head>
<body>

<div class="app-container">
    <!-- Header Bar -->
    <header class="app-header">
        <div class="header-title-container">
            <h1 class="app-title">PANTALLA DE COCINA (KDS)</h1>
            <div class="header-stats">Pedidos Activos: <span id="active-count"><?php echo count($comandas); ?></span></div>
        </div>
        <a href="menu.php" class="btn-back">← Volver al Menú</a>
    </header>

    <!-- KDS Display -->
    <main class="kds-container">
        <div class="kds-grid" id="kds-grid">
            <?php if (empty($comandas)): ?>
                <div class="empty-kds">
                    ✨ No hay comandas pendientes en cocina.
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