<?php
require_once 'SessionManager.php';
$session = new SessionManager();
$usuarioConectado = $_SESSION['usuario'] ?? 'Usuario Activo';

// 1. CONEXIÓN A LA BASE DE DATOS (Ajusta la contraseña si tu servidor local la requiere)
$host = '127.0.0.1';
$dbname = 'gestor_pedidos';
$username = 'root';
$password = ''; // Pon tu contraseña si tienes una configurada

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

// 2. PROCESAR ACTUALIZACIÓN DE ESTADO (Peticiones Fetch desde JS)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (isset($data['action']) && $data['action'] === 'update_status') {
        try {
            // Llama al procedimiento almacenado para actualizar el estado en la BD
            $stmt = $pdo->prepare("CALL sp_actualizar_estado_mesa(:id_mesa, :estado)");
            $stmt->execute([
                ':id_mesa' => $data['id_mesa'],
                ':estado' => $data['estado']
            ]);
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit; // Finaliza la ejecución para que no devuelva el HTML
    }
}

// 3. OBTENER MESAS DE LA BD AL CARGAR LA PÁGINA
$mesas_db = [];
try {
    // Llama al procedimiento almacenado para obtener la lista de mesas
    $stmt = $pdo->query("CALL sp_obtener_mesas()");
    $mesas_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor(); // Libera la conexión tras llamar al SP
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
                <?= $error_mesas ?>
            </div>
        <?php endif; ?>

        <div class="tables-grid" id="tables-container">
            <!-- Renderizado dinámico con JS -->
        </div>
    </main>

    <script>
        // Transfiere el arreglo de PHP a una variable de Javascript
        const mesas = <?php echo json_encode($mesas_db); ?>;
        
        const container = document.getElementById('tables-container');

        function renderTables() {
            container.innerHTML = '';
            
            mesas.forEach((mesa, index) => {
                const card = document.createElement('div');
                
                // Determina la apariencia basada en la base de datos
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
                
                // Lógica al tocar la mesa: Ir al menú
                card.onclick = (e) => {
                    // Evitar redirigir si se hizo clic en el botón
                    if (!e.target.classList.contains('btn-reserve') && !e.target.classList.contains('btn-free')) {
                        // Da formato al texto. Si en la BD la mesa es "1", la pasará a "MESA 01"
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

        // Función asíncrona para guardar el cambio de estado en la Base de Datos
        async function toggleStatus(index) {
            const mesa = mesas[index];
            
            // Lógica: Si está disponible, la reserva. Si está ocupada o reservada, la libera (disponible)
            const nuevoEstado = (mesa.estado === 'DISPONIBLE') ? 'RESERVADA' : 'DISPONIBLE';

            try {
                // Enviar la petición al backend (a este mismo archivo)
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
                    // Actualiza el arreglo local de javascript y vuelve a pintar las mesas
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

        // Ejecutar renderizado inicial
        document.addEventListener('DOMContentLoaded', renderTables);
    </script>
</body>
</html>