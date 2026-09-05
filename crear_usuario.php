<?php
// 1. Incluir el archivo de conexión
require_once 'conexion.php';

$mensaje = "";

// 2. Procesar la actualización de estado si se envía por POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'actualizar_estado') {
    $id_usuario_act = intval($_POST['id_usuario'] ?? 0);
    $nuevo_estado   = trim($_POST['nuevo_estado'] ?? '');

    if ($id_usuario_act > 0 && ($nuevo_estado == 'ACTIVO' || $nuevo_estado == 'INACTIVO')) {
        $sql_upd = "CALL actualizar_estado(?, ?)";       
        $stmt_upd = $conn->prepare("UPDATE usuarios SET estado = ? WHERE id_usuario = ?");
        if ($stmt_upd) {
            $stmt_upd->bind_param("si", $nuevo_estado, $id_usuario_act);
            if ($stmt_upd->execute()) {
                $mensaje = "<p style='color: green;'>¡Estado actualizado correctamente!</p>";
            } else {
                $mensaje = "<p style='color: red;'>Error al actualizar el estado: " . htmlspecialchars($stmt_upd->error) . "</p>";
            }
            $stmt_upd->close();
            while($conn->more_results() && $conn->next_result());
        }
    }
}

// 3. Procesar el registro de nuevo usuario si se envía por POST el formulario de registro
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'registrar_usuario') {
    $nombre_rol     = trim($_POST['rol'] ?? '');
    $descripcion_rol = "Rol asignado desde el registro web";
    $usuario_nuevo  = trim($_POST['usuario'] ?? '');
    $email_nuevo    = trim($_POST['email'] ?? '');
    $password_plano = trim($_POST['password'] ?? '');
    $estado_usuario = 'ACTIVO';

    if (!empty($nombre_rol) && !empty($usuario_nuevo) && !empty($email_nuevo) && !empty($password_plano)) {
        $sql = "CALL sp_crear_usuario_y_rol(?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("ssssss", $nombre_rol, $descripcion_rol, $usuario_nuevo, $email_nuevo, $password_plano, $estado_usuario);

            if ($stmt->execute()) {
                $mensaje = "<p style='color: green;'>¡Usuario y rol registrados con éxito!<br>Usuario: <b>" . htmlspecialchars($usuario_nuevo) . "</b></p>";
            } else {
                $mensaje = "<p style='color: red;'>Error al registrar (quizás el email ya exista): " . htmlspecialchars($stmt->error) . "</p>";
            }
            
            $stmt->close();
            while($conn->more_results() && $conn->next_result());
        } else {
            $mensaje = "<p style='color: red;'>Error al preparar la consulta: " . htmlspecialchars($conn->error) . "</p>";
        }
    } else {
        $mensaje = "<p style='color: orange;'>Por favor, completa todos los campos obligatorios para registrar.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
    <link rel="stylesheet" href="estilos.css">
    <link rel="stylesheet" href="tabla.css">
</head>
<body>

<div class="container-general">

    <h2>Panel de Gestión de Usuarios</h2>
    
    <!-- Mostramos los mensajes de éxito o error -->
    <?php echo $mensaje; ?>

    <!-- SECCIÓN 1: FORMULARIO DE REGISTRO -->
    <div class="form-container">
        <h3>Crear Nuevo Usuario</h3>
        <form action="" method="POST">
            <input type="hidden" name="accion" value="registrar_usuario">
            
            <div class="form-group">
                <label for="rol">Rol del Usuario:</label>
                <input type="text" id="rol" name="rol" placeholder="Ej: Administrador, Empleado, Cliente" required>
            </div>

            <div class="form-group">
                <label for="usuario">Nombre Completo:</label>
                <input type="text" id="usuario" name="usuario" required>
            </div>

            <div class="form-group">
                <label for="email">Correo Electrónico:</label>
                <input type="text" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit">Registrar</button>
        </form>
    </div>
    <a href="logout.php">
        <button type="button">Cerrar Sesión</button>
    </a>

</div>
<!-- SECCIÓN 2: TABLA DE USUARIOS REGISTRADOS -->
<div class="form-container2">
    <h3>Lista de Usuarios Registrados</h3>
    <div class="table-responsive">
        <table class="tabla-usuarios">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Rol</th>
                    <th>Email</th>
                    <th>Estado</th>
                    <th>Creado en</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Llamamos al procedimiento almacenado que trae los usuarios con su rol y sin contraseña
                $resultado = $conn->query("CALL sp_obtener_usuarios_con_rol()");

                if ($resultado && $resultado->num_rows > 0) {
                    while ($row = $resultado->fetch_assoc()) {
                        $id_user = $row['id_usuario'];
                        $estado_actual = $row['estado'];
                        $siguiente_estado = ($estado_actual == 'ACTIVO') ? 'INACTIVO' : 'ACTIVO';
                        $color_estado = ($estado_actual == 'ACTIVO') ? '#28a745' : '#dc3545';
                        
                        echo "<tr>";
                        echo "<td>" . $id_user . "</td>";
                        echo "<td>" . htmlspecialchars($row['nombre_usuario']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['nombre_rol']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                        echo "<td><b style='color: {$color_estado};'>" . $estado_actual . "</b></td>";
                        echo "<td>" . $row['creado_en'] . "</td>";
                        echo "<td>";
                        
                        // Formulario individual para cambiar el estado desde la misma tabla
                        echo "<form action='' method='POST' style='display:inline;'>";
                        echo "<input type='hidden' name='accion' value='actualizar_estado'>";
                        echo "<input type='hidden' name='id_usuario' value='" . $id_user . "'>";
                        echo "<input type='hidden' name='nuevo_estado' value='" . $siguiente_estado . "'>";
                        
                        $texto_boton = ($estado_actual == 'ACTIVO') ? 'Desactivar' : 'Activar';
                        $clase_btn = ($estado_actual == 'ACTIVO') ? 'btn-accion btn-desactivar' : 'btn-accion btn-activar';
                        
                        echo "<button type='submit' class='" . $clase_btn . "'>" . $texto_boton . "</button>";
                        echo "</form>";
                        
                        echo "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='7' class='mensaje-vacio'>No hay usuarios registrados.</td></tr>";
                }

                // Importante limpiar los resultados de MySQLi tras llamar un procedimiento almacenado
                if ($conn) {
                    while($conn->more_results() && $conn->next_result());
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>