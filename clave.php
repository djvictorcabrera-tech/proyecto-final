<?php
/**
 * ARCHIVO: clave.php
 * PROPÓSITO: Cambiar la contraseña mediante procedimientos almacenados y SweetAlert2.
 */

require_once 'conexion.php'; // Usa la variable $conn

$id_usuario = intval($_GET['id'] ?? 0);
$nombre_usuario = "";
$swal_script = "";

// 1. Obtener el nombre del usuario usando la columna 'nombre'
if ($id_usuario > 0) {
    $stmt_info = $conn->prepare("SELECT nombre FROM usuarios WHERE id_usuario = ?");
    if ($stmt_info) {
        $stmt_info->bind_param("i", $id_usuario);
        $stmt_info->execute();
        $res = $stmt_info->get_result();
        if ($row = $res->fetch_assoc()) {
            $nombre_usuario = $row['nombre'];
        }
        $stmt_info->close();
        while($conn->more_results() && $conn->next_result());
    }
}

// Redirigir si el usuario no existe
if ($id_usuario <= 0 || empty($nombre_usuario)) {
    header("Location: crear_usuario.php");
    exit();
}

// 2. Procesar el cambio de contraseña ejecutando el Procedimiento Almacenado
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'guardar_clave') {
    $nueva_pass_plana = trim($_POST['nueva_password'] ?? '');

    if (!empty($nueva_pass_plana)) {
        $password_hash = password_hash($nueva_pass_plana, PASSWORD_BCRYPT);

        // Llamada al procedimiento almacenado
        $sql_pass = "CALL sp_cambiar_password_usuario(?, ?)";
        $stmt_pass = $conn->prepare($sql_pass);

        if ($stmt_pass) {
            $stmt_pass->bind_param("is", $id_usuario, $password_hash);
            if ($stmt_pass->execute()) {
                $swal_script = "
                    Swal.fire({
                        title: '¡Clave cambiada con éxito!',
                        text: 'La contraseña ha sido actualizada correctamente.',
                        icon: 'success',
                        draggable: true
                    }).then(() => {
                        window.location.href = 'crear_usuario.php';
                    }).catch(() => {
                        window.location.href = 'crear_usuario.php';
                    });
                ";
            } else {
                $swal_script = "
                    Swal.fire({
                        title: 'Error',
                        text: 'No se pudo actualizar la contraseña.',
                        icon: 'error'
                    });
                ";
            }
            $stmt_pass->close();
            while($conn->more_results() && $conn->next_result());
        }
    } else {
        $swal_script = "
            Swal.fire({
                title: 'Atención',
                text: 'La contraseña no puede estar vacía.',
                icon: 'warning'
            });
        ";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña</title>
    <link rel="stylesheet" href="estilos.css">
    <link rel="stylesheet" href="clave.css">
    <link rel="stylesheet" href="normalize.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<div class="card-clave">
    <h3 style="margin-top:0; color:#333;">Cambiar Contraseña</h3>
    <p style="color:#666;">Usuario: <strong style="color:#000;"><?php echo htmlspecialchars($nombre_usuario); ?></strong></p>

    <form action="" method="POST">
        <input type="hidden" name="accion" value="guardar_clave">

        <div class="form-group-clave">
            <label for="nueva_password">Nueva Contraseña:</label>
            <input type="password" id="nueva_password" name="nueva_password" required placeholder="Ingresa la nueva clave" autofocus>
        </div>

        <div class="botones-clave">
            <a href="crear_usuario.php" class="btn-cancelar">Cancelar</a>
            <button type="submit" class="btn-guardar">Guardar</button>
        </div>
    </form>
</div>

<?php if (!empty($swal_script)): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php echo $swal_script; ?>
    });
</script>
<?php endif; ?>

</body>
</html>