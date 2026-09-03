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
    
    <style>
        :root {
            --bg-main: #0b111e;
            --bg-card: #151d2a;
            --bg-card-hover: #1c2638;
            --bg-sidebar: #0f172a;
            --text-primary: #ffffff;
            --text-muted: #8e9bb0;
            --color-primary: #3b82f6;
            --color-success: #10b981;
            --color-warning: #f59e0b;
            --color-danger: #ef4444;
            --border-color: #232f45;
            --radius-lg: 12px;
            --radius-md: 8px;
            --radius-sm: 6px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .app-header {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            padding: 16px 32px;
            background-color: var(--bg-sidebar);
            border-bottom: 1px solid var(--border-color);
        }

        .btn-back {
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
            transition: color 0.2s;
        }

        .btn-back:hover {
            color: var(--color-primary);
        }

        .app-title {
            color: var(--color-primary);
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            text-align: center;
        }

        .main-content {
            padding: 40px;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            flex-grow: 1;
        }

        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .tables-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 24px;
        }

        .table-card {
            background-color: var(--bg-card);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            transition: all 0.2s ease;
            cursor: pointer;
            position: relative;
        }

        .table-card:hover {
            transform: translateY(-4px);
            border-color: var(--color-primary);
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
        }

        .table-icon {
            width: 50px;
            height: 50px;
            background-color: #1e293b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .table-name {
            font-size: 1.2rem;
            font-weight: 700;
        }

        .table-capacity {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .table-status {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            text-transform: uppercase;
            margin-top: 4px;
        }

        /* ---------------- ESTILOS DE ESTADOS ---------------- */
        .status-available .table-status {
            background-color: rgba(16, 185, 129, 0.15);
            color: var(--color-success);
        }
        
        .status-reserved .table-status {
            background-color: rgba(245, 158, 11, 0.15);
            color: var(--color-warning);
        }
        .status-reserved {
            border-color: rgba(245, 158, 11, 0.3);
        }

        .status-occupied .table-status {
            background-color: rgba(239, 68, 68, 0.15);
            color: var(--color-danger);
        }
        .status-occupied {
            border-color: rgba(239, 68, 68, 0.3);
        }
        /* ---------------------------------------------------- */

        .btn-reserve {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
            margin-top: 10px;
        }

        .btn-reserve:hover {
            background-color: var(--color-warning);
            color: #fff;
            border-color: var(--color-warning);
        }
        
        .btn-free:hover {
            background-color: var(--color-success);
            color: #fff;
            border-color: var(--color-success);
        }
    </style>
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