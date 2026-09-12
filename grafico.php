<?php

    require_once 'conexion.php';
    require_once __DIR__ . '/jpgraph/src/jpgraph.php';
    require_once __DIR__ . '/jpgraph/src/jpgraph_bar.php';

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
    <link rel="stylesheet" href="estilos.css">
    <link rel="stylesheet" href="grafico.css">
    <link rel="stylesheet" href="normalize.css">
</head>
<body>
    <a href="crear_usuario.php" class="btn-volver">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
        </svg>
        Volver
    </a>

</body>
</html>