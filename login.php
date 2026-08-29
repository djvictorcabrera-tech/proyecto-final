<?php
// Incluir el archivo de conexión
require_once 'conexion.php';
require_once 'usuario.php';
require_once 'SessionManager.php';

$session = new SessionManager();
$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuarioInput  = trim($_POST['usuario'] ?? '');
    $passwordInput = trim($_POST['password'] ?? '');

    if (!empty($usuarioInput) && !empty($passwordInput)) {
        $usuario = new Usuario();

        if ($usuario->autenticar($usuarioInput, $passwordInput)) {
            // Guardar en sesión usando el objeto SessionManager
            $session->iniciarSesion($usuario->getId(), $usuario->getUsuario());
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
    <link rel="stylesheet" href="estilo.css">
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background-color: #f4f4f9; }
        .login-box { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); width: 300px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="password"] { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button { width: 100%; padding: 10px; background-color: #007bff; border: none; color: white; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background-color: #0056b3; }
        .error { color: red; margin-bottom: 15px; font-size: 14px; }
        .exito { color: green; margin-bottom: 15px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2 class="centrado">Iniciar Sesión</h2>
            <?php if (!empty($mensaje_error)): ?>
        <div class="error"><?php echo htmlspecialchars($mensaje_error); ?></div>
            <?php endif; ?>
         
        <?php if ($session->estaAutenticado()): ?>
            <div class="exito">
                ¡Bienvenido, <?php echo htmlspecialchars($session->getUsuarioNombre()); ?>!
            </div>
            <?php header("Location: menu.php");
            exit(); // Detiene la ejecución del script?>
        <?php else: ?>

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
        <a href="crear_usuario.php">
            <button type="submit">Crear Usuario</button>
        </a>
        <?php endif; ?>
    </div>
</body>
</html>