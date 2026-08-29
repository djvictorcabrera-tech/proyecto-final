<?php
// 1. Incluir el archivo de conexión
require_once 'conexion.php';

$mensaje = "";

// 2. Verificar si el formulario ha sido enviado por el método POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Capturamos los datos que el usuario escribió en el formulario
    $usuario_nuevo = $_POST['usuario'] ?? '';
    $password_plano = $_POST['password'] ?? '';

    // Validamos que los campos no estén vacíos
    if (!empty($usuario_nuevo) && !empty($password_plano)) {

        // 3. Encriptar la contraseña de forma segura usando password_hash
        $password_encriptado = password_hash($password_plano, PASSWORD_BCRYPT);

        // 4. Preparar la consulta SQL usando sentencias preparadas (Evita Inyección SQL)
        // Usamos INSERT para crear un usuario nuevo (o puedes usar UPDATE si ya existe)
        $sql = "INSERT INTO usuarios2 (usuario, password) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            // Asociamos los parámetros ("ss" significa que ambos son strings/cadenas de texto)
            $stmt->bind_param("ss", $usuario_nuevo, $password_encriptado);

            // Ejecutamos la consulta
            if ($stmt->execute()) {
                $mensaje = "<p style='color: green;'>¡Usuario registrado con éxito!<br>Usuario: <b>" . htmlspecialchars($usuario_nuevo) . "</b></p>";
            } else {
                $mensaje = "<p style='color: red;'>Error al registrar (quizás el usuario ya exista): " . $stmt->error . "</p>";
            }
            
            // Cerramos la sentencia
            $stmt->close();
        } else {
            $mensaje = "<p style='color: red;'>Error al preparar la consulta: " . $conn->error . "</p>";
        }
    } else {
        $mensaje = "<p style='color: orange;'>Por favor, completa todos los campos.</p>";
    }
}

// Cerramos la conexión a la base de datos
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Usuario</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; margin: 50px; }
        .form-container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0px 0px 10px rgba(0,0,0,0.1); max-width: 400px; margin: auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button { background-color: #2c3e50; color: white; padding: 10px; border: none; width: 100%; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background-color: #34495e; }
    </style>
</head>
<body>

<div class="form-control form-container">
    <h2>Crear Nuevo Usuario</h2>
    
    <!-- Mostramos los mensajes de éxito o error -->
    <?php echo $mensaje; ?>

    <!-- Formulario HTML que envía los datos a este mismo archivo mediante POST -->
    <form action="crear_usuario.php" method="POST">
        <div class="form-group">
            <label for="usuario">Nombre de Usuario:</label>
            <input type="text" id="usuario" name="usuario" required>
        </div>

        <div class="form-group">
            <label for="password">Contraseña:</label>
            <input type="password" id="password" name="password" required>
        </div>

        <button type="submit" action="crear_usuario.php">Registrar</button>
    </form>
    <a href="login.php">
        <br>
        <br>
        <button type="submit">Regresar</button>
    </a>
</div>

</body>
</html>