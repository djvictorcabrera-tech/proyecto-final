<?php
/**
 * ARCHIVO: login.php
 * PROPÓSITO: Controlar el acceso al sistema mediante autenticación de credenciales y redirección por rol.
 */

// Incluir dependencias base
require_once 'conexion.php';
require_once 'usuario.php';
require_once 'SessionManager.php';

$session = new SessionManager();
$mensaje_error = '';

// Si el usuario ya está autenticado, redirigir directamente al menú principal
if ($session->estaAutenticado()) {
    header("Location: menu.php");
    exit();
}

// Procesar el envío de credenciales mediante el formulario HTTP POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuarioInput  = trim($_POST['usuario'] ?? '');
    $passwordInput = trim($_POST['password'] ?? '');

    if (!empty($usuarioInput) && !empty($passwordInput)) {
        $usuarioObj = new Usuario();

        // Validar credenciales contra el modelo de datos
        if ($usuarioObj->autenticar($usuarioInput, $passwordInput)) {
            // Guardar datos en la sesión activa
            $session->iniciarSesion(
                $usuarioObj->getId(), 
                $usuarioObj->getUsuario(), 
                $usuarioObj->getRol()
            );

            // Redireccionar al panel de administración si el rol es Administrador, de lo contrario al Menú
            if ($usuarioObj->getRol() === 'administrador') {
                header("Location: crear_usuario.php");
                exit();
            } else {
                header("Location: menu.php");
                exit();
            }
        } else {
            // Capturar el mensaje de error específico definido en la clase Usuario
            $mensaje_error = $usuarioObj->getErrorMensaje();
        }
    } else {
        $mensaje_error = "Por favor, completa todos los campos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión</title>
    <link rel="stylesheet" href="estilos.css">
    <link rel="stylesheet" href="normalize.css">
</head>
<body>
    <div class="login-box">
        <h2 class="centrado form-group">Acceso Restaurante</h2>
        
        <?php if (!empty($mensaje_error)): ?>
            <div class="error"><?php echo htmlspecialchars($mensaje_error); ?></div>
        <?php endif; ?>
         
        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="usuario">Usuario:</label>
                <input type="text" id="usuario" name="usuario" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Ingresar</button>
            <br><br>
        </form>
    </div>
</body>
</html>