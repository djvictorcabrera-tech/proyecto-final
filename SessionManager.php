<?php
class SessionManager {

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function iniciarSesion($idUsuario, $nombreUsuario) {
        $_SESSION['usuario_id'] = $idUsuario;
        $_SESSION['usuario_nombre'] = $nombreUsuario;
    }

    public function estaAutenticado() {
        return isset($_SESSION['usuario_id']);
    }

    public function getUsuarioNombre() {
        return $_SESSION['usuario_nombre'] ?? '';
    }

    public function cerrarSesion() {
        session_unset();
        session_destroy();
    }
}
?>