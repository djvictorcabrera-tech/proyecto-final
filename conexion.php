<?php
$host = '192.168.1.180:80';
$user ='root';
$password = '';
$db = 'gestor_pedidos';

$conn = new mysqli($host, $user, $password, $db);
if($conn->connect_error){
    die("Error Critico de Conexion".$conn->connect_error);
}
$conn->set_charset ("utf8mb4");
echo"conexion exitosa";
?>
