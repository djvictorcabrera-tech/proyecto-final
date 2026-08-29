<?php
require_once 'conexion.php';

class Usuario {
    private $id;
    private $usuario;
    private $rol; // <--- Nueva propiedad para el rol
    private $db;

    public function __construct() {
        global $conn;
        $this->db = $conn;
    }

    // Getters
    public function getId() {
        return $this->id;
    }

    public function getUsuario() {
        return $this->usuario;
    }

    public function getRol() { // <--- Nuevo getter para el rol
        return $this->rol;
    }

    /**
     * Autentica un usuario mediante un procedimiento almacenado usando MySQLi
     */
    public function autenticar($usuarioInput, $passwordInput) {
        try {
            $stmt = $this->db->prepare("CALL sp_crear_usuario_y_rol(?)");
            
            if ($stmt) {
                $stmt->bind_param("s", $usuarioInput);
                $stmt->execute();
                
                $resultado = $stmt->get_result();
                $data = $resultado->fetch_assoc();
                
                $stmt->close();

                // Limpieza de buffers obligatoria en MySQLi con procedimientos almacenados
                while($this->db->more_results() && $this->db->next_result());

                // Verificamos la contraseña encriptada (o SHA2 si lo manejas directo en BD)
                if ($data && password_verify($passwordInput, $data['password'])) {
                    $this->id = $data['id_usuario']; // Asegúrate que coincida con tu columna
                    $this->usuario = $data['nombre']; // Asegúrate que coincida con tu columna
                    $this->rol = $data['nombre_rol']; // <--- Guardamos el rol (ej: 'Administrador', 'Cliente')
                    
                    return true;
                }
            }
            return false;
        } catch (Exception $e) {
            error_log("Error en autenticación: " . $e->getMessage());
            return false;
        }
    }
}
?>