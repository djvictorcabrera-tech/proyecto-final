<?php
// 1. INICIALIZACIÓN Y GESTIÓN DE SESIÓN
require_once 'SessionManager.php';
$session = new SessionManager();

$usuarioConectado = $_SESSION['usuario'] ?? $_SESSION['user_name'] ?? $_SESSION['nombre'] ?? $_SESSION['user'] ?? 'Usuario Activo';
$mesaActiva = $_GET['mesa'] ?? 'MESA 01';

// ---------------- CONEXIÓN A LA BASE DE DATOS ----------------
$host = '127.0.0.1';
$dbname = 'gestor_pedidos';
$username = 'root';
$password = ''; // Coloca aquí tu contraseña si la tienes

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
// -------------------------------------------------------------

// 2. PROCESAMIENTO DE PETICIONES HTTP POST (RECEPCIÓN DE COMANDAS)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!empty($data['items'])) { // Verifica que el carrito no esté vacío
        try {
            // Iniciar transacción para asegurar que ambos SP se ejecuten correctamente
            $pdo->beginTransaction();

            // Extraer el ID numérico de la mesa (filtra texto como "MESA 01" dejando solo "1")
            $idMesa = (int) filter_var($data['mesa'], FILTER_SANITIZE_NUMBER_INT);
            if ($idMesa === 0) $idMesa = 1; // Fallback por seguridad
            
            $totalPedido = (float) $data['total'];

            // 1. Crear el pedido principal llamando a sp_crear_pedido
            $stmt = $pdo->prepare("CALL sp_crear_pedido(:id_mesa, :total, @p_id_pedido)");
            $stmt->bindParam(':id_mesa', $idMesa, PDO::PARAM_INT);
            $stmt->bindParam(':total', $totalPedido, PDO::PARAM_STR);
            $stmt->execute();
            $stmt->closeCursor();

            // 2. Recuperar el ID del pedido (order_id) generado por la variable de salida (OUT)
            $result = $pdo->query("SELECT @p_id_pedido AS id_pedido")->fetch(PDO::FETCH_ASSOC);
            $idPedidoGenerado = $result['id_pedido'];

            // 3. Preparar el llamado para insertar los detalles del carrito
            $stmtDetalle = $pdo->prepare("CALL sp_agregar_detalle_pedido(:id_pedido, :id_producto, :cantidad, :precio)");
            
            foreach ($data['items'] as $item) { // Itera sobre cada producto del carrito[cite: 2]
                $idProducto = (int) $item['id'];
                $cantidad = (int) $item['qty'];
                $precio = (float) $item['price'];

                $stmtDetalle->bindParam(':id_pedido', $idPedidoGenerado, PDO::PARAM_INT);
                $stmtDetalle->bindParam(':id_producto', $idProducto, PDO::PARAM_INT);
                $stmtDetalle->bindParam(':cantidad', $cantidad, PDO::PARAM_INT);
                $stmtDetalle->bindParam(':precio', $precio, PDO::PARAM_STR);
                $stmtDetalle->execute();
                $stmtDetalle->closeCursor();
            }

            // Confirmar transacción si todo salió bien
            $pdo->commit();

            echo json_encode([
                'status' => 'success',
                'message' => "¡Comanda #{$idPedidoGenerado} enviada a cocina exitosamente!",
                'order_id' => $idPedidoGenerado,
                'redirect' => 'menu_cocina.php' // Redirige a cocina según tu lógica original[cite: 2]
            ]);
        } catch (Exception $e) {
            // Revertir cambios en la base de datos si ocurre un error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode([
                'status' => 'error',
                'message' => 'Error en la base de datos: ' . $e->getMessage()
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'El carrito está vacío.' // Mensaje de error si no hay items[cite: 2]
        ]);
    }
    exit;
}

// 3. OBTENER PLATOS Y BEBIDAS DE LA BD
$productos_db = [];
try {
    // Llamada al procedimiento almacenado
    $stmt = $pdo->query("CALL sp_obtener_platos_bebidas()");
    $productos_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    // Formatear los precios para asegurar que JS los reciba como números y no como texto
    foreach ($productos_db as &$prod) {
        $prod['price'] = (float) $prod['price'];
    }
} catch (Exception $e) {
    $error_productos = "Error al cargar el menú: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestor de Pedidos</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="menu.css">
</head>
<body>

<!-- 4. ESTRUCTURA DE LA INTERFAZ HTML -->
<div class="app-container">
        <header class="app-header">
        <div style="display: flex; align-items: center; gap: 16px;">
            <h1 class="app-title">Menu</h1>
            <a href="menu_cocina.php" style="background-color: #3b82f6; color: #ffffff; padding: 6px 14px; border-radius: 6px; font-size: 0.85rem; font-weight: 700; text-decoration: none; transition: background-color 0.2s;">
            Cocina
            </a>
         </div>
    
        <div class="user-center-display">
        <span class="user-status-dot"></span>
        <span><?php echo htmlspecialchars($usuarioConectado); ?></span>
        </div>

    <div class="header-actions">
        <div class="table-select-container">
            <span class="table-select-label">Ubicación:</span>
            <a href="mesas.php" class="table-select" style="text-decoration: none; display: flex; align-items: center; gap: 8px;">
                <?php echo htmlspecialchars($mesaActiva); ?>
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path></svg>
            </a>
            <input type="hidden" id="select-mesa" value="<?php echo htmlspecialchars($mesaActiva); ?>">
        </div>
        
        <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </header>

    <div class="main-layout">
        <section class="menu-section">
            <?php if (isset($error_productos)): ?>
                <div style="background: rgba(239, 68, 68, 0.1); padding: 15px; border-radius: 8px; color: var(--color-danger); text-align: center; margin-bottom: 20px;">
                    <?= $error_productos ?>
                </div>
            <?php endif; ?>

            <div class="categories-filter">
                <button class="category-btn active" onclick="filterCategory('comida', this)">Comida</button>
                <button class="category-btn" onclick="filterCategory('bebidas', this)">Bebidas</button>
                <button class="category-btn" onclick="filterCategory('postres', this)">Postres</button>
            </div>

            <div class="products-grid" id="products-container"></div>
        </section>

        <aside class="order-section">
            <div>
                <h2 class="order-header-title">TU PEDIDO</h2>
                <ul class="cart-list" id="cart-items"></ul>
            </div>

            <div class="order-footer">
                <div class="total-row">
                    <span class="total-label">Total:</span>
                    <span class="total-amount" id="cart-total">$0.00</span>
                </div>
                <button class="btn-submit" id="btn-kitchen" onclick="sendToKitchen()">Enviar a Cocina</button>
            </div>
        </aside>
    </div>
</div>

<!-- 5. LÓGICA DE CLIENTE EN JAVASCRIPT -->
<script>
    // Base de datos inyectada directamente desde PHP a JavaScript
    const dbProducts = <?php echo json_encode($productos_db); ?>;

    let cart = [];
    let currentCategory = 'comida';

    function renderProducts() {
        const container = document.getElementById('products-container');
        container.innerHTML = ''; 

        // Si no hay productos, mostramos un aviso
        if (dbProducts.length === 0) {
            container.innerHTML = '<div style="color: var(--text-muted);">No hay productos registrados en la base de datos.</div>';
            return;
        }

        const filtered = dbProducts.filter(p => p.category === currentCategory);

        filtered.forEach(p => {
            const card = document.createElement('div');
            card.className = 'product-card';
            
            // Validar si existe imagen_url para mostrar la foto o un texto por defecto
            const imageHtml = p.imagen_url 
                ? `<img src="${p.imagen_url}" alt="${p.name}" style="width: 100%; height: 100%; object-fit: cover; border-radius: var(--radius-sm);">` 
                : `Foto`;

            card.innerHTML = `
                <div class="product-thumb" style="padding: 0; overflow: hidden; background: transparent;">
                    ${imageHtml}
                </div>
                <div class="product-info">
                    <div class="product-title">${p.name}</div>
                    <div class="product-desc">${p.desc || ''}</div>
                    <div class="product-price">$${p.price.toFixed(2)}</div>
                </div>
                <button class="btn-add" onclick="changeQuantity(${p.id}, 1)">+</button>
            `;
            container.appendChild(card);
        });
    }

    function filterCategory(cat, btn) {
        currentCategory = cat;
        document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderProducts();
    }

    function changeQuantity(productId, delta) {
        const existingIndex = cart.findIndex(item => item.id === productId);

        if (existingIndex !== -1) {
            cart[existingIndex].qty += delta;

            if (cart[existingIndex].qty <= 0) {
                cart.splice(existingIndex, 1);
            }
        } else if (delta > 0) {
            const prod = dbProducts.find(p => p.id === productId);
            if (prod) {
                cart.push({ id: prod.id, name: prod.name, qty: 1, price: prod.price });
            }
        }
        renderCart();
    }

    function renderCart() {
        const cartContainer = document.getElementById('cart-items');
        const totalEl = document.getElementById('cart-total');
        const submitBtn = document.getElementById('btn-kitchen');

        cartContainer.innerHTML = '';
        let total = 0;

        if (cart.length === 0) {
            cartContainer.innerHTML = '<div class="empty-cart-msg">El carrito está vacío</div>';
            submitBtn.disabled = true; 
        } else {
            submitBtn.disabled = false;
            cart.forEach(item => {
                const itemTotal = item.price * item.qty;
                total += itemTotal;

                const li = document.createElement('li');
                li.className = 'cart-item';
                li.innerHTML = `
                    <div class="cart-item-details">
                        <span class="cart-item-name">${item.name}</span>
                        <span class="cart-item-price">$${itemTotal.toFixed(2)}</span>
                    </div>
                    <div class="cart-item-controls">
                        <button class="btn-qty btn-minus" onclick="changeQuantity(${item.id}, -1)">-</button>
                        <span class="cart-qty-num">${item.qty}</span>
                        <button class="btn-qty btn-plus" onclick="changeQuantity(${item.id}, 1)">+</button>
                    </div>
                `;
                cartContainer.appendChild(li);
            });
        }

        totalEl.textContent = `$${total.toFixed(2)}`;
    }

    async function sendToKitchen() {
        if (cart.length === 0) return;

        const mesaSeleccionada = document.getElementById('select-mesa').value;
        const totalCalculated = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);

        const payload = {
            mesa: mesaSeleccionada,
            items: cart,
            total: totalCalculated
        };

        try {
            const response = await fetch('menu.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const result = await response.json();
            if (result.status === 'success') {
                cart = []; 
                renderCart();
                window.location.href = result.redirect || 'menu_cocina.php';
            } else {
                alert(`❌ Error: ${result.message}`);
            }
        } catch (error) {
            console.error('Error al enviar la comanda:', error);
            alert('❌ Ocurrió un error al enviar el pedido a cocina.');
        }
    }

    window.addEventListener('DOMContentLoaded', () => {
        renderProducts();
        renderCart();
    });
</script>   

</body>
</html>