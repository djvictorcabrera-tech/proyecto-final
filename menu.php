<?php
/**
 * ARCHIVO: menu.php
 * PROPÓSITO: Punto de venta principal. Carga el catálogo de productos, gestiona el carrito cliente y envía comandas.
 */

require_once 'SessionManager.php';
require_once 'conexion.php'; 
$session = new SessionManager();

if (!$session->estaAutenticado()) {
    header("Location: login.php");
    exit();
}

$usuarioConectado = $session->getUsuarioNombre() ?: 'Usuario Activo';
$mesaActiva = $_GET['mesa'] ?? 'MESA 01';

// 1. PROCESAMIENTO DE COMANDAS RECIBIDAS POR PETICIÓN POST JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!empty($data['items'])) {
        try {
            // Inicio de transacción de base de datos
            $conn->begin_transaction();

            $idMesa = (int) filter_var($data['mesa'], FILTER_SANITIZE_NUMBER_INT);
            if ($idMesa === 0) $idMesa = 1;
            
            $totalPedido = (float) $data['total'];

            // A) Llamar sp_crear_pedido
            $stmt = $conn->prepare("CALL sp_crear_pedido(?, ?, @p_id_pedido)");
            $stmt->bind_param("id", $idMesa, $totalPedido);
            $stmt->execute();
            $stmt->close();

            // B) Obtener el ID del pedido generado
            $resQuery = $conn->query("SELECT @p_id_pedido AS id_pedido");
            $result = $resQuery->fetch_assoc();
            $idPedidoGenerado = $result['id_pedido'];
            $resQuery->free();

            while ($conn->more_results() && $conn->next_result()) {
                if ($res = $conn->store_result()) $res->free();
            }

            // C) Insertar cada ítem del carrito vía sp_agregar_detalle_pedido
            $stmtDetalle = $conn->prepare("CALL sp_agregar_detalle_pedido(?, ?, ?, ?)");
            
            foreach ($data['items'] as $item) {
                $idProducto = (int) $item['id'];
                $cantidad   = (int) $item['qty'];
                $precio     = (float) $item['price'];

                $stmtDetalle->bind_param("iiid", $idPedidoGenerado, $idProducto, $cantidad, $precio);
                $stmtDetalle->execute();

                while ($conn->more_results() && $conn->next_result()) {
                    if ($res = $conn->store_result()) $res->free();
                }
            }
            $stmtDetalle->close();

            // Confirmar transacción completa
            $conn->commit();

            echo json_encode([
                'status' => 'success',
                'message' => "¡Comanda #{$idPedidoGenerado} enviada a cocina exitosamente!",
                'order_id' => $idPedidoGenerado,
                'redirect' => 'menu_cocina.php'
            ]);
        } catch (Exception $e) {
            // Revertir cambios en la BD si falla alguna operación
            $conn->rollback();
            echo json_encode([
                'status' => 'error',
                'message' => 'Error en la base de datos: ' . $e->getMessage()
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'El carrito está vacío.'
        ]);
    }
    exit;
}

// 2. CONSULTAR EL MENÚ COMPLETO (Platos y Bebidas)
$productos_db = [];
try {
    $result = $conn->query("CALL sp_obtener_platos_bebidas()");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['price'] = (float) $row['price'];
            $productos_db[] = $row;
        }
        $result->free();
        
        while ($conn->more_results() && $conn->next_result()) {
            if ($res = $conn->store_result()) $res->free();
        }
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
    <link rel="stylesheet" href="menu.css">
    <link rel="stylesheet" href="normalize.css">
</head>
<body>

<div class="app-container">
    <header class="app-header">
        <div style="display: flex; align-items: center; gap: 16px;">
            <h1 class="app-title">Menu</h1>
            <a href="menu_cocina.php" style="background-color: #3b82f6; color: #ffffff; padding: 6px 14px; border-radius: 6px; font-size: 0.85rem; font-weight: 700; text-decoration: none;">Cocina</a>
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
                    <?= htmlspecialchars($error_productos) ?>
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

<script>
    // Inyección de la base de datos de productos desde PHP
    const dbProducts = <?php echo json_encode($productos_db); ?>;
    let cart = [];
    let currentCategory = 'comida';

    // Renderizar productos dinámicamente filtrando por categoría
    function renderProducts() {
        const container = document.getElementById('products-container');
        container.innerHTML = ''; 

        if (!dbProducts || dbProducts.length === 0) {
            container.innerHTML = '<div style="color: var(--text-muted);">No hay productos registrados en la base de datos.</div>';
            return;
        }

        const filtered = dbProducts.filter(p => {
            const cat = (p.categoria || p.category || p.nombre_categoria || '').toString().toLowerCase();
            return cat.includes(currentCategory.toLowerCase());
        });

        if (filtered.length === 0) {
            container.innerHTML = '<div style="color: var(--text-muted); padding: 20px;">No hay productos en esta categoría.</div>';
            return;
        }

        filtered.forEach(p => {
            const id = Number(p.id_producto || p.id);
            const nombre = p.nombre || p.name || 'Sin nombre';
            const descripcion = p.descripcion || p.desc || '';
            const precio = parseFloat(p.precio || p.price || 0);

            const card = document.createElement('div');
            card.className = 'product-card';
            
            const imageHtml = p.imagen_url 
                ? `<img src="${p.imagen_url}" alt="${nombre}" style="width: 100%; height: 100%; object-fit: cover; border-radius: var(--radius-sm);">` 
                : `Foto`;

            card.innerHTML = `
                <div class="product-thumb" style="padding: 0; overflow: hidden; background: transparent;">${imageHtml}</div>
                <div class="product-info">
                    <div class="product-title">${nombre}</div>
                    <div class="product-desc">${descripcion}</div>
                    <div class="product-price">$${precio.toFixed(2)}</div>
                </div>
                <button class="btn-add" onclick="changeQuantity(${id}, 1)">+</button>
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

    // Incrementar/Decrementar cantidad de ítems en el carrito
    function changeQuantity(productId, delta) {
        const cleanId = Number(productId);
        const existingIndex = cart.findIndex(item => Number(item.id) === cleanId);

        if (existingIndex !== -1) {
            cart[existingIndex].qty += delta;

            if (cart[existingIndex].qty <= 0) {
                cart.splice(existingIndex, 1);
            }
        } else if (delta > 0) {
            const prod = dbProducts.find(p => Number(p.id_producto || p.id) === cleanId);
            
            if (prod) {
                cart.push({ 
                    id: Number(prod.id_producto || prod.id), 
                    name: prod.nombre || prod.name, 
                    qty: 1, 
                    price: parseFloat(prod.precio || prod.price || 0) 
                });
            }
        }
        renderCart();
    }

    // Dibujar el carrito en el HTML
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

    // Enviar el carrito por Fetch POST a menu.php
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