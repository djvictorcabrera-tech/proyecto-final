<?php
// 1. Incluir el archivo de conexión
require_once 'conexion.php';

$mensaje = "";

// 2. Verificar si el formulario ha sido enviado por el método POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Capturamos los datos del formulario (incluyendo el rol)
    $nombre_rol     = trim($_POST['rol'] ?? '');
    $descripcion_rol = "Rol asignado desde el registro web"; // Descripción por defecto o puedes agregar un input
    $usuario_nuevo  = trim($_POST['usuario'] ?? '');
    $email_nuevo    = trim($_POST['email'] ?? ''); // Necesitamos el email según tu tabla usuarios
    $password_plano = trim($_POST['password'] ?? '');
    $estado_usuario = 'ACTIVO';

    // Validamos que los campos no estén vacíos
    if (!empty($nombre_rol) && !empty($usuario_nuevo) && !empty($email_nuevo) && !empty($password_plano)) {

        // NOTA: Como usaremos el procedimiento almacenado con SHA2 dentro de MySQL, 
        // le pasamos la contraseña en texto plano y la base de datos la encripta.
        
        // 3. Llamar al procedimiento almacenado con 6 parámetros (?)
        $sql = "CALL sp_crear_usuario_y_rol(?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            // "ssssss" significa que los 6 parámetros son cadenas de texto (strings)
            $stmt->bind_param("ssssss", $nombre_rol, $descripcion_rol, $usuario_nuevo, $email_nuevo, $password_plano, $estado_usuario);

            // Ejecutamos la consulta
            if ($stmt->execute()) {
                $mensaje = "<p style='color: green;'>¡Usuario y rol registrados con éxito!<br>Usuario: <b>" . htmlspecialchars($usuario_nuevo) . "</b></p>";
            } else {
                $mensaje = "<p style='color: red;'>Error al registrar (quizás el email ya exista): " . htmlspecialchars($stmt->error) . "</p>";
            }
            
            // Cerramos la sentencia y limpiamos buffers de procedimientos almacenados en MySQLi
            $stmt->close();
            while($conn->more_results() && $conn->next_result());

        } else {
            $mensaje = "<p style='color: red;'>Error al preparar la consulta: " . htmlspecialchars($conn->error) . "</p>";
        }
    } else {
        $mensaje = "<p style='color: orange;'>Por favor, completa todos los campos obligatorios.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Usuario</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

<div class="form-control form-container">
    <h2>Crear Nuevo Usuario</h2>
    
    <!-- Mostramos los mensajes de éxito o error -->
    <?php echo $mensaje; ?>

    <!-- Formulario HTML actualizado con los campos que exige tu base de datos -->
    <form action="crear_usuario.php" method="POST">
        
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
    
    <a href="logout.php">
        <br>
        <button type="button">Cerrar Sesion</button>
    </a>
</div>

</body>
</html>