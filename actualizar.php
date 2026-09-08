<?php
/**
 * ARCHIVO: actualizar.php
 * PROPÓSITO: Módulo de actualización rápida de precios e inventario/disponibilidad para platos y bebidas.
 */

require_once 'conexion.php';
require_once 'SessionManager.php';

$session = new SessionManager();

// Control de acceso para usuarios autenticados
if (!$session->estaAutenticado()) {
    header("Location: login.php");
    exit();
}

$mensaje_exito = '';
$mensaje_error = '';

// 1. PROCESAR ACTUALIZACIÓN INDIVIDUAL DE PRODUCTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar'])) {
    // Saneamiento y validación de tipos de entrada
    $id_producto = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT);
    $precio      = filter_input(INPUT_POST, 'precio', FILTER_VALIDATE_FLOAT);
    $disponible  = filter_input(INPUT_POST, 'disponible', FILTER_VALIDATE_INT);

    if ($id_producto !== false && $precio !== false && $disponible !== false && $disponible >= 0) {
        try {
            // Invocar sp_actualizar_precio_disponible
            $stmt = $conn->prepare("CALL sp_actualizar_precio_disponible(?, ?, ?)");
            $stmt->bind_param("idi", $id_producto, $precio, $disponible);
            
            if ($stmt->execute()) {
                $mensaje_exito = "Producto actualizado correctamente.";
            } else {
                $mensaje_error = "No se pudo actualizar el producto.";
            }
            $stmt->close();

            // Vaciado del búfer de resultados de MySQLi
            while ($conn->more_results() && $conn->next_result()) {
                if ($res = $conn->store_result()) {
                    $res->free();
                }
            }

        } catch (mysqli_sql_exception $e) {
            $mensaje_error = "Error al actualizar: " . $e->getMessage();
        }
    } else {
        $mensaje_error = "Por favor, ingresa un precio y una cantidad de stock válidos.";
    }
}

// 2. CONSULTAR LISTADO COMPLETO DE PRODUCTOS
$productos = [];
try {
    $resultado = $conn->query("CALL sp_obtener_platos_bebidas2()");
    if ($resultado) {
        $productos = $resultado->fetch_all(MYSQLI_ASSOC);
        $resultado->free();
    }

    // Limpieza de buffer
    while ($conn->more_results() && $conn->next_result()) {
        if ($res = $conn->store_result()) {
            $res->free();
        }
    }
} catch (mysqli_sql_exception $e) {
    $mensaje_error = "Error al obtener los registros: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Actualizar Platos y Bebidas</title>
    <link rel="stylesheet" href="actualizar.css">
    <link rel="stylesheet" href="normalize.css">
</head>
<body>

    <a href="crear_usuario.php" class="btn-volver">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
        </svg>
        Volver
    </a>

    <div class="container">
        <h2>Gestión de Precios e Inventario / Cantidad</h2>

        <?php if (!empty($mensaje_exito)): ?>
            <div class="mensaje-exito"><?php echo htmlspecialchars($mensaje_exito); ?></div>
        <?php endif; ?>

        <?php if (!empty($mensaje_error)): ?>
            <div class="mensaje-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Precio ($)</th>
                    <th>Cantidad Disponible</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($productos) > 0): ?>
                    <?php foreach ($productos as $producto): ?>
                        <tr>
                            <!-- Formulario independiente por fila para actualizar cada ítem -->
                            <form method="POST" action="actualizar.php">
                                <td>
                                    <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                                    <input type="hidden" name="id_producto" value="<?php echo $producto['id_producto']; ?>">
                                </td>
                                <td class="col-desc">
                                    <?php echo htmlspecialchars($producto['descripcion'] ?? 'Sin descripción'); ?>
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" class="input-inline" name="precio" value="<?php echo htmlspecialchars($producto['precio']); ?>" required>
                                </td>
                                <td>
                                    <input type="number" min="0" step="1" class="input-inline" name="disponible" value="<?php echo htmlspecialchars($producto['disponible']); ?>" required>
                                </td>
                                <td>
                                    <button type="submit" name="actualizar" class="btn-actualizar">Guardar</button>
                                </td>
                            </form>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">No hay productos registrados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>