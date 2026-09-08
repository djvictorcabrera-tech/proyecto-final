<?php
/**
 * ARCHIVO: conexion.php
 * PROPÓSITO: Configuración y establecimiento de la conexión centralizada a la base de datos MySQL.
 */

// Habilitar reporte estricto de errores para MySQLi (lanza excepciones de tipo mysqli_sql_exception)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Parámetros de conexión a la base de datos
$host = 'localhost';
$user = 'root';
$password = '';
$db = 'gestor_pedidos';

try {
    // Instanciar la extensión MySQLi
    $conn = new mysqli();
    
    // Configurar tiempo de espera máximo para intentar la conexión (5 segundos)
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    
    // Abrir la conexión real con el servidor de BD
    $conn->real_connect($host, $user, $password, $db);
    
    // Configurar el juego de caracteres a UTF-8 completo para soporte de tildes y caracteres especiales
    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    // Captura de errores de conexión y despliegue de mensaje de diagnóstico
    echo "<div style='color: red; font-family: sans-serif; padding: 10px; border: 1px solid red;'>";
    echo "<strong>Error de conexión remota:</strong> " . $e->getMessage() . "<br><br>";
    echo "<em>Sugerencia: Asegúrate de que la PC (192.168.1.180) tenga MySQL activo, el puerto 3306 abierto y el 'bind-address' en 0.0.0.0.</em>";
    echo "</div>";
    exit();
}
?>