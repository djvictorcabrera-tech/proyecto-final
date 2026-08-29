<?php
// Habilitar reporte estricto de errores para MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = 'localhost';
$user = 'root';
$password = '';
$db = 'gestor_pedidos';

try {
    $conn = new mysqli();
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    
    // Agregamos el $port al final de la función real_connect
    $conn->real_connect($host, $user, $password, $db);
    
    // Si la conexión es exitosa
    $conn->set_charset("utf8mb4");
    /*echo "¡Conexión exitosa con el servidor remoto!";*/

} catch (mysqli_sql_exception $e) {
    echo "<div style='color: red; font-family: sans-serif; padding: 10px; border: 1px solid red;'>";
    echo "<strong>Error de conexión remota:</strong> " . $e->getMessage() . "<br><br>";
    echo "<em>Sugerencia: Asegúrate de que la PC (192.168.1.180) tenga MySQL activo, el puerto 3306 abierto y el 'bind-address' en 0.0.0.0.</em>";
    echo "</div>";
    exit();
}
?>