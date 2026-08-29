<?php
class SessionManager {

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // Modificado para aceptar y guardar el rol
    public function iniciarSesion($idUsuario, $nombreUsuario, $rolUsuario) {
        $_SESSION['usuario_id'] = $idUsuario;
        $_SESSION['usuario_nombre'] = $nombreUsuario;
        $_SESSION['usuario_rol'] = $rolUsuario; // <--- Guardamos el rol
    }

    public function estaAutenticado() {
        return isset($_SESSION['usuario_id']);
    }

    public function getUsuarioNombre() {
        return $_SESSION['usuario_nombre'] ?? '';
    }

    // Nuevo método para obtener el rol fácilmente desde cualquier vista
    public function getUsuarioRol() {
        return $_SESSION['usuario_rol'] ?? '';
    }

    public function cerrarSesion() {
        session_unset();
        session_destroy();
    }
}
?>