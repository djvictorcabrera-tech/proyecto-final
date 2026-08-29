<?php
require_once 'SessionManager.php';

$session = new SessionManager();

// Si tu SessionManager tiene un método como cerrarSesion() o destruirSesion(), úsalo aquí.
// Ejemplo: $session->cerrarSesion();

// Garantizar la destrucción manual si no hay método expuesto:
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Redirigir al inicio de sesión
header("Location: login.php");
exit();
?>