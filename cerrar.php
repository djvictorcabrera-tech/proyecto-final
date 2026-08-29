<?php
// 1. Iniciar la sesión existente para poder acceder a ella
session_start();

// 2. Destruir todas las variables de sesión
$_SESSION = array();

// 3. Si se desea destruir la sesión completamente, borra también la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Finalmente, destruir la sesión en el servidor
session_destroy();

// 5. Redirigir al usuario al formulario de login o página principal
header("location: index.php");
exit;
?>