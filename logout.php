<?php
/**
 * ARCHIVO: logout.php
 * PROPÓSITO: Destruir completamente la sesión activa del usuario y desvincular cookies asociadas.
 */

require_once 'SessionManager.php';

$session = new SessionManager();

// Asegurar el inicio de sesión para su destrucción completa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vaciar todas las variables globales de $_SESSION
$_SESSION = array();

// Eliminar la cookie de sesión del navegador si está configurada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir la sesión en el servidor
session_destroy();

// Redirigir a la pantalla de login
header("Location: login.php");
exit();
?>