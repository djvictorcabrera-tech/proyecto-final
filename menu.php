<?php
// 1. INICIALIZACIÓN Y GESTIÓN DE SESIÓN
// Incluye la clase encargada de manejar la sesión y la instancia para garantizar que la sesión PHP esté activa.
require_once 'SessionManager.php';
$session = new SessionManager();

// Captura el nombre del usuario desde la variable súper global $_SESSION.
// Utiliza el operador de fusión de nulos (??) para evaluar múltiples claves habituales de login ('usuario', 'user_name', etc.).
// Si ninguna existe, asigna 'Usuario Activo' como valor por defecto.
$usuarioConectado = $_SESSION['usuario'] ?? $_SESSION['user_name'] ?? $_SESSION['nombre'] ?? $_SESSION['user'] ?? 'Usuario Activo';

// 2. PROCESAMIENTO DE PETICIONES HTTP POST (RECEPCIÓN DE COMANDAS)
// Evalúa si la página está recibiendo una solicitud enviada desde JavaScript mediante el método POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Configura la cabecera de respuesta para indicarle al cliente que devolverá un formato JSON.
    header('Content-Type: application/json');
    
    // Lee el flujo de entrada primario de la petición (payload en JSON enviado via fetch/AJAX).
    $rawInput = file_get_contents('php://input');
    
    // Decodifica el JSON recibido y lo convierte en un arreglo asociativo de PHP.
    $data = json_decode($rawInput, true);

    // Verifica que el arreglo devuelto contenga productos en el parámetro 'items'.
    if (!empty($data['items'])) {
        // Asigna el número de mesa seleccionado o 'MESA 01' en caso de no ser provisto.
        $numMesa = $data['mesa'] ?? 'MESA 01';
        
        // Retorna una respuesta exitosa codificada en JSON con el mensaje, un ID aleatorio de pedido y la ruta de redirección.
        echo json_encode([
            'status' => 'success',
            'message' => "¡Comanda enviada a cocina exitosamente para la {$numMesa}!",
            'order_id' => rand(1000, 9999),
            'redirect' => 'menu_cocina.php'
        ]);
    } else {
        // Retorna una respuesta de error si el carrito fue enviado vacío.
        echo json_encode([
            'status' => 'error',
            'message' => 'El carrito está vacío.'
        ]);
    }
    // Finaliza la ejecución del script PHP para evitar renderizar el código HTML dentro de la respuesta AJAX.
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestor de Pedidos y Menú Digital Interactivo</title>
    
    <!-- Carga de fuentes externas desde Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* 3. HOJA DE ESTILOS CSS */
        
        /* Definición de variables CSS globales (Custom Properties) para consistencia de diseño */
        :root {
            --bg-main: #0b111e;
            --bg-card: #151d2a;
            --bg-card-hover: #1c2638;
            --bg-sidebar: #0f172a;
            --text-primary: #ffffff;
            --text-muted: #8e9bb0;
            
            --color-primary: #3b82f6;
            --color-primary-hover: #2563eb;
            --color-primary-dark: #1d4ed8;
            --color-danger: #ef4444;

            --border-color: #232f45;
            --radius-lg: 12px;
            --radius-md: 8px;
            --radius-sm: 6px;
        }

        /* Reseteo general de márgenes, rellenos y modelo de caja */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        /* Bloqueo de desplazamiento general para emular una aplicación web de pantalla completa (POS) */
        html, body {
            width: 100%;
            height: 100%;
            background-color: var(--bg-main);
            color: var(--text-primary);
            overflow: hidden;
        }

        /* Contenedor principal ocupando todo el viewport */
        .app-container {
            width: 100vw;
            height: 100vh;
            display: flex;
            flex-direction: column;
            background: #111827;
        }

        /* Diseños de cabecera en tres columnas usando Grid (Título, Usuario, Acciones) */
        .app-header {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            padding: 16px 32px;
            background-color: var(--bg-sidebar);
            border-bottom: 1px solid var(--border-color);
            flex-shrink: 0;
        }

        .app-title {
            color: var(--color-primary);
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        /* Indicador dinámico visual del usuario conectado */
        .user-center-display {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Punto verde indicador de estado activo/en línea */
        .user-status-dot {
            width: 8px;
            height: 8px;
            background-color: #10b981;
            border-radius: 50%;
            box-shadow: 0 0 8px rgba(16, 185, 129, 0.6);
        }

        .header-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 20px;
        }

        .table-select-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-select-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-select {
            background-color: var(--border-color);
            color: #ffffff;
            font-weight: 700;
            font-size: 0.9rem;
            padding: 8px 14px;
            border-radius: var(--radius-sm);
            border: 1px solid #334155;
            outline: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .table-select:hover, .table-select:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
        }

        .table-select option {
            background-color: var(--bg-sidebar);
            color: #ffffff;
        }

        .btn-logout {
            background-color: transparent;
            color: var(--color-danger);
            border: 1px solid var(--color-danger);
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-logout:hover {
            background-color: var(--color-danger);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        /* Disposición principal de 2 columnas: Menú interactivo (izquierda) y Carrito (derecha) */
        .main-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            flex-grow: 1;
            height: calc(100vh - 65px);
            overflow: hidden;
        }

        .menu-section {
            padding: 32px;
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            gap: 24px;
            overflow-y: auto;
        }

        .categories-filter {
            display: flex;
            gap: 12px;
        }

        .category-btn {
            background-color: var(--bg-card);
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            padding: 10px 22px;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .category-btn:hover {
            color: var(--text-primary);
            background-color: var(--bg-card-hover);
        }

        .category-btn.active {
            background-color: var(--color-primary);
            color: #ffffff;
            border-color: var(--color-primary);
            font-weight: 700;
            box-shadow: 0 4px 14px rgba(59, 130, 246, 0.3);
        }

        /* Cuadrícula responsiva para las tarjetas de productos */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .product-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
            transition: transform 0.15s ease, border-color 0.15s ease;
        }

        .product-card:hover {
            transform: translateY(-2px);
            border-color: #334155;
            background-color: var(--bg-card-hover);
        }

        .product-thumb {
            width: 64px;
            height: 64px;
            background-color: #243044;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 700;
            flex-shrink: 0;
            text-transform: uppercase;
        }

        .product-info {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding-right: 36px;
        }

        .product-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .product-desc {
            font-size: 0.78rem;
            color: var(--text-muted);
            line-height: 1.3;
        }

        .product-price {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--color-primary);
            margin-top: 4px;
        }

        .btn-add {
            position: absolute;
            right: 14px;
            bottom: 14px;
            width: 32px;
            height: 32px;
            background-color: var(--color-primary);
            color: #ffffff;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 1.3rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s;
        }

        .btn-add:hover {
            background-color: var(--color-primary-hover);
            transform: scale(1.05);
        }

        /* Estilos del panel lateral (Carrito / Comanda) */
        .order-section {
            background-color: var(--bg-sidebar);
            padding: 32px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            overflow: hidden;
        }

        .order-header-title {
            font-size: 1.1rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            margin-bottom: 24px;
            color: #f1f5f9;
        }

        .cart-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 16px;
            max-height: calc(100vh - 280px);
            overflow-y: auto;
            padding-right: 6px;
        }

        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            background-color: var(--bg-card);
            padding: 12px 14px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
        }

        .cart-item-details {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .cart-item-name {
            color: #f8fafc;
            font-weight: 600;
        }

        .cart-item-price {
            font-weight: 700;
            color: var(--color-primary);
            font-size: 0.85rem;
        }

        .cart-item-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            background-color: var(--bg-main);
            padding: 4px 8px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
        }

        .btn-qty {
            width: 24px;
            height: 24px;
            border: none;
            border-radius: 4px;
            font-weight: 800;
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s;
        }

        .btn-minus {
            background-color: #334155;
            color: #ffffff;
        }

        .btn-minus:hover {
            background-color: var(--color-danger);
        }

        .btn-plus {
            background-color: var(--color-primary);
            color: #ffffff;
        }

        .btn-plus:hover {
            background-color: var(--color-primary-hover);
        }

        .cart-qty-num {
            font-weight: 700;
            min-width: 18px;
            text-align: center;
            font-size: 0.85rem;
        }

        .empty-cart-msg {
            color: var(--text-muted);
            font-size: 0.9rem;
            text-align: center;
            margin-top: 40px;
            font-style: italic;
        }

        .order-footer {
            border-top: 1px solid var(--border-color);
            padding-top: 20px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .total-label {
            font-size: 1rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        .total-amount {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--color-primary);
        }

        .btn-submit {
            width: 100%;
            background-color: var(--color-primary);
            color: #ffffff;
            border: none;
            padding: 14px;
            border-radius: var(--radius-md);
            font-weight: 800;
            font-size: 0.9rem;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(59, 130, 246, 0.3);
        }

        .btn-submit:hover {
            background-color: var(--color-primary-hover);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }

        .btn-submit:disabled {
            background-color: #334155;
            color: #64748b;
            cursor: not-allowed;
            box-shadow: none;
        }
    </style>
</head>
<body>

<!-- 4. ESTRUCTURA DE LA INTERFAZ HTML -->
<div class="app-container">
    <header class="app-header">
        <h1 class="app-title">Menú Digital</h1>
        
        <!-- Muestra dinámica del usuario sanitizada previamente con htmlspecialchars en PHP -->
        <div class="user-center-display">
            <span class="user-status-dot"></span>
            <span><?php echo htmlspecialchars($usuarioConectado); ?></span>
        </div>

        <div class="header-actions">
            <div class="table-select-container">
                <span class="table-select-label">Ubicación:</span>
                <!-- Selector de mesas; 'MESA 01' configurada con el atributo selected -->
                <select id="select-mesa" class="table-select">
                    <option value="MESA 01" selected>MESA 01</option>
                    <option value="MESA 02">MESA 02</option>
                    <option value="MESA 03">MESA 03</option>
                    <option value="MESA 04">MESA 04</option>
                    <option value="MESA 05">MESA 05</option>
                    <option value="MESA 06">MESA 06</option>
                </select>
            </div>
            
            <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </header>

    <div class="main-layout">
        <!-- Sección izquierda: Selector de categorías y catálogo de productos -->
        <section class="menu-section">
            <div class="categories-filter">
                <button class="category-btn active" onclick="filterCategory('hamburguesas', this)">Hamburguesas</button>
                <button class="category-btn" onclick="filterCategory('bebidas', this)">Bebidas</button>
                <button class="category-btn" onclick="filterCategory('postres', this)">Postres</button>
            </div>

            <!-- Contenedor dinámico donde JavaScript inyectará las tarjetas de productos -->
            <div class="products-grid" id="products-container"></div>
        </section>

        <!-- Sección derecha: Panel de pedido/carrito de compras -->
        <aside class="order-section">
            <div>
                <h2 class="order-header-title">TU PEDIDO</h2>
                <!-- Lista dinámica donde JavaScript insertará los productos seleccionados -->
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
    // Base de datos simulada de productos disponibles en el menú
    const dbProducts = [
        { id: 1, name: 'Burger Clásica', desc: 'Carne 180g y cheddar', price: 8.50, category: 'hamburguesas' },
        { id: 2, name: 'Burger Doble', desc: 'Doble carne y tocino', price: 11.00, category: 'hamburguesas' },
        { id: 3, name: 'Refresco 500ml', desc: 'Bebida helada', price: 2.00, category: 'bebidas' },
        { id: 4, name: 'Jugo Natural', desc: 'Sabor a naranja', price: 3.00, category: 'bebidas' },
        { id: 5, name: 'Helado Sundae', desc: 'Chocolate y galleta', price: 4.50, category: 'postres' }
    ];

    // Arreglo del carrito inicializado totalmente vacío al cargar la vista
    let cart = [];

    // Estado local para la categoría visible actualmente
    let currentCategory = 'hamburguesas';

    // Función para renderizar dinámicamente los productos según la categoría activa
    function renderProducts() {
        const container = document.getElementById('products-container');
        container.innerHTML = ''; // Limpia el contenedor antes de dibujar

        // Filtra el arreglo según la categoría activa
        const filtered = dbProducts.filter(p => p.category === currentCategory);

        // Genera el HTML de cada tarjeta de producto y lo agrega al DOM
        filtered.forEach(p => {
            const card = document.createElement('div');
            card.className = 'product-card';
            card.innerHTML = `
                <div class="product-thumb">Foto</div>
                <div class="product-info">
                    <div class="product-title">${p.name}</div>
                    <div class="product-desc">${p.desc}</div>
                    <div class="product-price">$${p.price.toFixed(2)}</div>
                </div>
                <button class="btn-add" onclick="changeQuantity(${p.id}, 1)">+</button>
            `;
            container.appendChild(card);
        });
    }

    // Función para cambiar de categoría al hacer clic en los botones superiores
    function filterCategory(cat, btn) {
        currentCategory = cat;
        // Remueve la clase 'active' de todos los botones y se la asigna al seleccionado
        document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderProducts();
    }

    // Función central para modificar cantidades o agregar/remover elementos del carrito
    function changeQuantity(productId, delta) {
        // Busca si el producto ya está dentro del carrito
        const existingIndex = cart.findIndex(item => item.id === productId);

        if (existingIndex !== -1) {
            // Modifica la cantidad actual según el valor recibido (+1 o -1)
            cart[existingIndex].qty += delta;

            // Si la cantidad llega a 0 o menos, elimina el elemento del arreglo
            if (cart[existingIndex].qty <= 0) {
                cart.splice(existingIndex, 1);
            }
        } else if (delta > 0) {
            // Si el producto no estaba y se incrementa, se busca en dbProducts y se inserta al carrito
            const prod = dbProducts.find(p => p.id === productId);
            if (prod) {
                cart.push({ id: prod.id, name: prod.name, qty: 1, price: prod.price });
            }
        }
        // Actualiza el renderizado del carrito
        renderCart();
    }

    // Función para renderizar la lista del carrito y actualizar totales/botones
    function renderCart() {
        const cartContainer = document.getElementById('cart-items');
        const totalEl = document.getElementById('cart-total');
        const submitBtn = document.getElementById('btn-kitchen');

        cartContainer.innerHTML = '';
        let total = 0;

        // Caso: Carrito sin items
        if (cart.length === 0) {
            cartContainer.innerHTML = '<div class="empty-cart-msg">El carrito está vacío</div>';
            submitBtn.disabled = true; // Deshabilita el botón de enviar
        } else {
            // Caso: Carrito con productos
            submitBtn.disabled = false;
            cart.forEach(item => {
                const itemTotal = item.price * item.qty;
                total += itemTotal;

                // Genera el ítem de la lista con botones para incrementar/decrementar
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

        // Muestra el total acumulado en pantalla
        totalEl.textContent = `$${total.toFixed(2)}`;
    }

    // Función asíncrona para enviar la orden hacia el servidor PHP mediante Fetch API
    async function sendToKitchen() {
        if (cart.length === 0) return;

        // Captura la mesa seleccionada en el elemento <select>
        const mesaSeleccionada = document.getElementById('select-mesa').value;
        const totalCalculated = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);

        // Estructura el objeto/payload a transmitir en JSON
        const payload = {
            mesa: mesaSeleccionada,
            items: cart,
            total: totalCalculated
        };

        try {
            // Envía la petición POST al script actual ('menu.php')
            const response = await fetch('menu.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            // Procesa la respuesta JSON emitida por PHP
            const result = await response.json();
            if (result.status === 'success') {
                cart = []; // Vacía el carrito local tras confirmación
                renderCart();
                // Redirige a la vista de cocina especificada en la respuesta
                window.location.href = result.redirect || 'menu_cocina.php';
            } else {
                alert(`❌ Error: ${result.message}`);
            }
        } catch (error) {
            console.error('Error al enviar la comanda:', error);
            alert('❌ Ocurrió un error al enviar el pedido a cocina.');
        }
    }

    // Evento de inicialización que ejecuta el renderizado inicial al cargar completamente el DOM
    window.addEventListener('DOMContentLoaded', () => {
        renderProducts();
        renderCart();
    });
</script>   

</body>
</html>