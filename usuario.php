<?php
require_once 'conexion.php';

class Usuario {
    private $id;
    private $usuario;
    private $rol;
    private $errorMensaje = ""; // <--- Propiedad para guardar mensajes específicos
    private $db;

    public function __construct() {
        global $conn;
        $this->db = $conn;
    }

    public function getId() {
        return $this->id;
    }

    public function getUsuario() {
        return $this->usuario;
    }

    public function getRol() {
        return $this->rol;
    }

    public function getErrorMensaje() {
        return $this->errorMensaje;
    }

    public function autenticar($usuarioInput, $passwordInput) {
        try {
            // Nota: Hacemos una consulta previa o ajustamos el procedure si queremos detectar el estado inactivo exacto,
            // pero con el procedure actual, si no trae nada, validamos si el usuario al menos existe pero está inactivo.
            
            $stmt = $this->db->prepare("CALL sp_obtener_usuario_login(?)");
            
            if ($stmt) {
                $stmt->bind_param("s", $usuarioInput);
                $stmt->execute();
                
                $resultado = $stmt->get_result();
                $data = $resultado->fetch_assoc();
                
                $stmt->close();
                while($this->db->more_results() && $this->db->next_result());

                if ($data) {
                    $password_ingresada_hash = hash('sha256', $passwordInput);

                    if ($password_ingresada_hash === $data['password']) {
                        // Verificación extra por seguridad en PHP
                        if ($data['estado'] === 'INACTIVO') {
                            $this->errorMensaje = "Su usuario se encuentra inactivo.";
                            return false;
                        }

                        $this->id = $data['id'];            
                        $this->usuario = $data['usuario'];    
                        $this->rol = $data['nombre_rol'];     
                        
                        return true;
                    } else {
                        $this->errorMensaje = "Usuario o contraseña incorrectos.";
                    }
                } else {
                    $this->errorMensaje = "Usuario o contraseña incorrectos.";
                }
            }
            return false;
        } catch (Exception $e) {
            error_log("Error en autenticación: " . $e->getMessage());
            $this->errorMensaje = "Ocurrió un error en el sistema.";
            return false;
        }
    }
}
?>