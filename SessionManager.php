<?php
/**
 * CLASE: SessionManager
 * PROPÓSITO: Abstraer y gestionar la sesión de usuario (autenticación, variables globales de sesión y cierre).
 */
class SessionManager {

    /**
     * Constructor: Inicia la sesión nativa de PHP solo si no se encuentra activa.
     */
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Registra las credenciales principales del usuario en la sesión.
     * 
     * @param int $idUsuario
     * @param string $nombreUsuario
     * @param string $rolUsuario
     */
    public function iniciarSesion($idUsuario, $nombreUsuario, $rolUsuario) {
        $_SESSION['usuario_id'] = $idUsuario;
        $_SESSION['usuario_nombre'] = $nombreUsuario;
        $_SESSION['usuario_rol'] = $rolUsuario;
    }

    /**
     * Comprueba si existe un usuario autenticado.
     * 
     * @return bool
     */
    public function estaAutenticado() {
        return isset($_SESSION['usuario_id']);
    }

    /**
     * Retorna el nombre del usuario activo en sesión.
     * 
     * @return string
     */
    public function getUsuarioNombre() {
        return $_SESSION['usuario_nombre'] ?? '';
    }

    /**
     * Retorna el rol asignado al usuario en sesión.
     * 
     * @return string
     */
    public function getUsuarioRol() {
        return $_SESSION['usuario_rol'] ?? '';
    }

    /**
     * Limpia y destruye los datos de la sesión activa.
     */
    public function cerrarSesion() {
        session_unset();
        session_destroy();
    }
}
?>