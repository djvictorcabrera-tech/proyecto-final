<?php
/**
 * ARCHIVO: mesas.php
 * PROPÓSITO: Plano interactivo para visualización y actualización de estado de mesas (Disponible, Reservada, Ocupada).
 */

require_once 'SessionManager.php';
require_once 'conexion.php';

$session = new SessionManager();

if (!$session->estaAutenticado()) {
    header("Location: login.php");
    exit();
}

$usuarioConectado = $session->getUsuarioNombre() ?: 'Usuario Activo';

// 1. PROCESAR PETICIÓN AJAX (POST JSON) PARA CAMBIAR EL ESTADO DE UNA MESA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (isset($data['action']) && $data['action'] === 'update_status') {
        try {
            // Ejecución del Stored Procedure sp_actualizar_estado_mesa
            $stmt = $conn->prepare("CALL sp_actualizar_estado_mesa(?, ?)");
            $stmt->bind_param("is", $data['id_mesa'], $data['estado']);
            $stmt->execute();
            $stmt->close();

            // Vaciado de búfer
            while ($conn->more_results() && $conn->next_result()) {
                if ($res = $conn->store_result()) $res->free();
            }

            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// 2. CONSULTAR LISTA DE MESAS DESDE LA BD
$mesas_db = [];
try {
    $result = $conn->query("CALL sp_obtener_mesas()");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $mesas_db[] = $row;
        }
        $result->free();

        while ($conn->more_results() && $conn->next_result()) {
            if ($res = $conn->store_result()) $res->free();
        }
    }
} catch (Exception $e) {
    $error_mesas = "Error al cargar mesas: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selección de Mesas</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="mesa.css">
    <link rel="stylesheet" href="normalize.css">
</head>
<body>

    <header class="app-header">
        <div>
            <a href="menu.php" class="btn-back">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"></path></svg>
                Volver al Menú
            </a>
        </div>
        <h1 class="app-title">Mesas</h1>
        <div style="text-align: right; color: var(--text-muted); font-size: 0.9rem;">
            <?= htmlspecialchars($usuarioConectado) ?>
        </div>
    </header>

    <main class="main-content">
        <div class="page-header">
            <h2>Selecciona una ubicación</h2>
            <div style="display: flex; gap: 15px; font-size: 0.85rem; color: var(--text-muted);">
                <span style="display: flex; align-items: center; gap: 5px;"><div style="width: 10px; height: 10px; border-radius: 50%; background: var(--color-success);"></div> Disponible</span>
                <span style="display: flex; align-items: center; gap: 5px;"><div style="width: 10px; height: 10px; border-radius: 50%; background: var(--color-warning);"></div> Reservada</span>
                <span style="display: flex; align-items: center; gap: 5px;"><div style="width: 10px; height: 10px; border-radius: 50%; background: var(--color-danger);"></div> Ocupada</span>
            </div>
        </div>

        <?php if (isset($error_mesas)): ?>
            <div style="background: rgba(239, 68, 68, 0.1); padding: 15px; border-radius: 8px; color: var(--color-danger); text-align: center;">
                <?= htmlspecialchars($error_mesas) ?>
            </div>
        <?php endif; ?>

        <div class="tables-grid" id="tables-container"></div>
    </main>

    <script>
        // Arreglo JS con la información del servidor PHP
        const mesas = <?php echo json_encode($mesas_db); ?>;
        const container = document.getElementById('tables-container');

        // Función para renderizar dinámicamente las mesas en pantalla
        function renderTables() {
            container.innerHTML = '';
            
            mesas.forEach((mesa, index) => {
                const card = document.createElement('div');
                
                let statusClass = 'status-available';
                let btnClass = 'btn-reserve';
                let btnText = 'Marcar para Reserva';
                
                if (mesa.estado === 'RESERVADA') {
                    statusClass = 'status-reserved';
                    btnText = 'Liberar Mesa';
                    btnClass = 'btn-free';
                } else if (mesa.estado === 'OCUPADA') {
                    statusClass = 'status-occupied';
                    btnText = 'Liberar Mesa';
                    btnClass = 'btn-free';
                }

                card.className = `table-card ${statusClass}`;
                
                // Redirección al menú con la mesa seleccionada
                card.onclick = (e) => {
                    if (!e.target.classList.contains('btn-reserve') && !e.target.classList.contains('btn-free')) {
                        const numMesaFormateado = mesa.numero_mesa.padStart(2, '0');
                        window.location.href = `menu.php?mesa=MESA%20${numMesaFormateado}`;
                    }
                };

                card.innerHTML = `
                    <div class="table-icon">🪑</div>
                    <div class="table-name">MESA ${mesa.numero_mesa.padStart(2, '0')}</div>
                    <div class="table-capacity">👤 ${mesa.capacidad} personas</div>
                    <div class="table-status">${mesa.estado}</div>
                    <button class="${btnClass} ${btnClass === 'btn-reserve' ? 'btn-reserve' : 'btn-reserve btn-free'}" onclick="toggleStatus(${index})">${btnText}</button>
                `;
                container.appendChild(card);
            });
        }

        // Envío del cambio de estado mediante Fetch API
        async function toggleStatus(index) {
            const mesa = mesas[index];
            const nuevoEstado = (mesa.estado === 'DISPONIBLE') ? 'RESERVADA' : 'DISPONIBLE';

            try {
                const response = await fetch('mesas.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_status',
                        id_mesa: mesa.id_mesa,
                        estado: nuevoEstado
                    })
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    mesas[index].estado = nuevoEstado;
                    renderTables();
                } else {
                    alert('❌ Ocurrió un error al guardar: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error de comunicación con el servidor.');
            }
        }

        document.addEventListener('DOMContentLoaded', renderTables);
    </script>
</body>
</html>