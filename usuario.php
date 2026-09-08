<?php
/**
 * CLASE: Usuario
 * 
 * Gestiona el modelo de datos del usuario y la lógica de negocio para la
 * autenticación en el sistema mediante el procedimiento almacenado 'sp_autenticar_usuario'.
 * 
 * @package    GestorPedidos
 * @subpackage Modelos
 */

require_once 'conexion.php';

class Usuario {
    
    /**
     * Instancia de la conexión a la base de datos MySQLi.
     * @var mysqli
     */
    private $conn;

    /**
     * Identificador único del usuario autenticado (id_usuario).
     * @var int|null
     */
    private $id;

    /**
     * Nombre completo o de usuario de la cuenta autenticada.
     * @var string|null
     */
    private $usuario;

    /**
     * Nombre del rol asignado al usuario (ej. 'Administrador', 'Empleado').
     * @var string|null
     */
    private $rol;

    /**
     * Almacena mensajes detallados de error durante el proceso de autenticación.
     * @var string|null
     */
    private $errorMensaje;

    /**
     * Constructor de la clase.
     * Inicializa la propiedad $conn utilizando la variable global de conexión definida en conexion.php.
     */
    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }

    /**
     * Autentica un usuario verificando sus credenciales e historial de acceso.
     *
     * Realiza una llamada al procedimiento almacenado 'sp_autenticar_usuario', 
     * valida el estado de la cuenta ('ACTIVO') y compara el hash de la contraseña 
     * utilizando password_verify().
     *
     * @param string $usuarioInput  Nombre de usuario o correo electrónico ingresado en el login.
     * @param string $passwordInput Contraseña en texto plano ingresada en el formulario.
     * 
     * @return bool True si las credenciales son correctas y el usuario está activo; False en caso contrario.
     */
    public function autenticar($usuarioInput, $passwordInput) {
        try {
            // 1. Preparar la llamada al Stored Procedure usando consultas preparadas
            $sql = "CALL sp_autenticar_usuario(?)";
            $stmt = $this->conn->prepare($sql);
            
            // Validar que la preparación del statement no haya fallado
            if (!$stmt) {
                $this->errorMensaje = "Error en la consulta de autenticación: " . $this->conn->error;
                return false;
            }

            // 2. Vincular parámetros de entrada (s = string: se evalúa contra nombre o email)
            $stmt->bind_param("s", $usuarioInput);
            $stmt->execute();
            $resultado = $stmt->get_result();

            // 3. Evaluar si se encontró un registro coincidente
            if ($row = $resultado->fetch_assoc()) {
                
                // Cierre de statement y limpieza del búfer de MySQL para prevenir el error "Commands out of sync"
                $stmt->close();
                while ($this->conn->more_results() && $this->conn->next_result());

                // Validar el estado operativo de la cuenta de usuario
                if ($row['estado'] !== 'ACTIVO') {
                    $this->errorMensaje = "La cuenta se encuentra inactiva.";
                    return false;
                }

                // 4. Verificar la contraseña ingresada con el hash Bcrypt guardado en la BD
                if (password_verify($passwordInput, $row['password'])) {
                    // Credenciales válidas: Guardar los atributos de sesión del usuario
                    $this->id      = $row['id_usuario'];
                    $this->usuario = $row['nombre'];
                    $this->rol     = $row['nombre_rol'];
                    return true;
                } else {
                    $this->errorMensaje = "Contraseña incorrecta.";
                    return false;
                }
            } else {
                // Limpieza del búfer de MySQL en caso de que no exista el usuario
                $stmt->close();
                while ($this->conn->more_results() && $this->conn->next_result());
                
                $this->errorMensaje = "El usuario no existe.";
                return false;
            }
        } catch (Exception $e) {
            // Capturar excepciones globales y guardar el mensaje
            $this->errorMensaje = "Error en el sistema: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Obtiene el ID del usuario autenticado.
     * @return int|null
     */
    public function getId() { 
        return $this->id; 
    }

    /**
     * Obtiene el nombre del usuario autenticado.
     * @return string|null
     */
    public function getUsuario() { 
        return $this->usuario; 
    }

    /**
     * Obtiene el rol del usuario autenticado.
     * @return string|null
     */
    public function getRol() { 
        return $this->rol; 
    }

    /**
     * Obtiene el último mensaje de error registrado en la clase.
     * @return string|null
     */
    public function getErrorMensaje() { 
        return $this->errorMensaje; 
    }
}
?>