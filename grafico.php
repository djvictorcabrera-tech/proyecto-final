<?php
// 1. Incluir el archivo de conexión centralizado a la BD
require_once 'conexion.php'; // Usa la variable $conn[cite: 1]
// 2. Cargar las librerías de JPGraph
require_once 'jpgraph/src/jpgraph.php';
require_once 'jpgraph/src/jpgraph_bar.php';

try {
    // 3. Ejecutar el procedimiento almacenado
    $sql = "CALL sp_obtener_ventas_por_mes()";
    $resultado = $conn->query($sql);

    $meses = [];
    $ventas = [];

    // Recorrer los resultados del procedimiento
    while ($fila = $resultado->fetch_assoc()) {
        $meses[] = $fila['periodo'];        // Formato: 'YYYY-MM'
        $ventas[] = (float)$fila['total_ventas']; // Sumatoria en $
    }

    $resultado->free();

    while ($conn->more_results() && $conn->next_result()) {
        if ($res = $conn->store_result()) {
            $res->free();
        }
    }

    if (empty($ventas)) {
        die("No se encontraron registros de ventas entregadas para graficar.");
    }

    // 4. Configurar el lienzo de la gráfica
    $graph = new Graph(800, 450);
    $graph->SetScale("textlin");
    $graph->SetMargin(70, 30, 50, 80);
    
    $graph->title->Set("Ventas Totales por Mes");
    $graph->title->SetFont(FF_FONT1, FS_BOLD);
    
    $graph->xaxis->title->Set("Periodo (Año-Mes)");
    $graph->xaxis->SetTickLabels($meses);
    $graph->xaxis->SetLabelAngle(45);

    $graph->yaxis->title->Set("Monto Total ($)");

    // 5. Crear las barras
    $barplot = new BarPlot($ventas);
    $barplot->SetFillColor('cadetblue');

    $barplot->value->Show();
    $barplot->value->SetFormat('$%.2f');
    $barplot->value->SetFont(FF_FONT1, FS_BOLD);

    $graph->Add($barplot);

    // 6. Generar la imagen binaria en el navegador
    ob_clean(); // Limpia cualquier texto o warning del buffer
    $graph->Stroke();

} catch (Exception $e) {
    die("Error al generar la gráfica de ventas: " . $e->getMessage());
}
?>