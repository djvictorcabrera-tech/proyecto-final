<?php
require_once 'conexion.php';

class Usuario {
    private $id;
    private $usuario;
    private $db;

    public function __construct() {
        // Importamos la variable $conn definida en tu conexion.php
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

    /**
     * Autentica un usuario mediante un procedimiento almacenado usando MySQLi
     */
    public function autenticar($usuarioInput, $passwordInput) {
        try {
            // 1. En MySQLi usamos el signo '?' en lugar de ':usuario'
            $stmt = $this->db->prepare("CALL sp_obtener_usuario_login(?)");
            
            if ($stmt) {
                // 2. Vinculamos el parámetro como texto ('s')
                $stmt->bind_param("s", $usuarioInput);
                
                // 3. Ejecutamos la consulta
                $stmt->execute();
                
                // 4. Obtenemos el resultado de la base de datos
                $resultado = $stmt->get_result();
                $data = $resultado->fetch_assoc();
                
                // 5. Cerramos el statement
                $stmt->close();

                // 6. LIMPIEZA DE BUFFERS (Obligatorio en MySQLi al usar CALL)
                // Esto evita que las siguientes consultas de tu sistema fallen
                while($this->db->more_results() && $this->db->next_result());

                // 7. Verificamos la contraseña encriptada
                if ($data && password_verify($passwordInput, $data['password'])) {
                    $this->id = $data['id'];
                    $this->usuario = $data['usuario'];
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