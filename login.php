<?php
// Incluir el archivo de conexión
require_once 'conexion.php';
require_once 'usuario.php';
require_once 'SessionManager.php';

$session = new SessionManager();
$mensaje_error = '';

// Si ya está autenticado, redirigir directamente al menú antes de enviar HTML
if ($session->estaAutenticado()) {
    header("Location: menu.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuarioInput  = trim($_POST['usuario'] ?? '');
    $passwordInput = trim($_POST['password'] ?? '');

    if (!empty($usuarioInput) && !empty($passwordInput)) {
        $usuario = new Usuario();

        if ($usuario->autenticar($usuarioInput, $passwordInput)) {
            // CORREGIDO: Pasamos el ID, el nombre Y el rol al SessionManager
            $session->iniciarSesion(
                $usuario->getId(), 
                $usuario->getUsuario(), 
                $usuario->getRol()
            );

            // Redirigir inmediatamente después de iniciar sesión con éxito
            header("Location: menu.php");
            exit();
        } else {
            $mensaje_error = "Usuario o contraseña incorrectos.";
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
    <style>
        
    </style>
</head>
<body>
    <div class="login-box">
        <h2 class="centrado">Acceso Restaurante</h2>
        
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
            <br>
            <br>
        </form>
    </div>
</body>
</html>