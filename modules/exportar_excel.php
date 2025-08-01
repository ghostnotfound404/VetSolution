<?php
require '../includes/config.php';

// Obtener la tabla a exportar
$tabla = isset($_GET['tabla']) ? $_GET['tabla'] : '';

// Configurar el nombre del archivo según la tabla
$nombre_archivo = "Exportacion";
switch ($tabla) {
    case 'clientes':
        $nombre_archivo = "ExportacionClientes";
        break;
    case 'mascotas':
        $nombre_archivo = "ExportacionMascotas";
        break;
    case 'productos':
        $nombre_archivo = "ExportacionProductos";
        break;
    case 'servicios':
        $nombre_archivo = "ExportacionServicios";
        break;
    default:
        $nombre_archivo = "Exportacion";
}

// Agregar fecha para hacer el nombre único
$nombre_archivo .= "_" . date("dmY") . ".xls";

// Librería para exportar a Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');

// Variable para la tabla a exportar
$tabla = isset($_GET['tabla']) ? $_GET['tabla'] : '';

function exportar_clientes($conn) {
    $sql = "SELECT nombre, apellido, celular, dni, direccion, fecha_registro FROM clientes ORDER BY fecha_registro DESC";
    $result = $conn->query($sql);
    echo "<table border='1'>";
    echo "<tr><th>Nombre</th><th>Apellido</th><th>Celular</th><th>DNI</th><th>Dirección</th><th>Fecha de Registro</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
        echo "<td>" . htmlspecialchars($row['apellido']) . "</td>";
        echo "<td>" . htmlspecialchars($row['celular']) . "</td>";
        echo "<td>" . htmlspecialchars($row['dni']) . "</td>";
        echo "<td>" . htmlspecialchars($row['direccion']) . "</td>";
        echo "<td>" . htmlspecialchars($row['fecha_registro']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

function exportar_mascotas($conn) {
    $sql = "SELECT m.nombre AS mascota_nombre, m.especie, m.raza, m.sexo, m.fecha_nacimiento, c.nombre AS propietario_nombre, c.apellido AS propietario_apellido
            FROM mascotas m
            LEFT JOIN clientes c ON m.cliente_id = c.id
            ORDER BY m.fecha_registro DESC";
    $result = $conn->query($sql);
    echo "<table border='1'>";
    echo "<tr><th>Nombre</th><th>Especie</th><th>Raza</th><th>Sexo</th><th>Fecha Nacimiento</th><th>Propietario</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['mascota_nombre']) . "</td>";
        echo "<td>" . htmlspecialchars($row['especie']) . "</td>";
        echo "<td>" . htmlspecialchars($row['raza']) . "</td>";
        echo "<td>" . htmlspecialchars($row['sexo']) . "</td>";
        echo "<td>" . htmlspecialchars($row['fecha_nacimiento']) . "</td>";
        echo "<td>" . htmlspecialchars($row['propietario_nombre'] . ' ' . $row['propietario_apellido']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

function exportar_productos($conn) {
    $sql = "SELECT nombre, descripcion, precio, stock, categoria FROM productos ORDER BY id DESC";
    $result = $conn->query($sql);
    echo "<table border='1'>";
    echo "<tr><th>Nombre</th><th>Descripción</th><th>Precio</th><th>Stock</th><th>Categoría</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
        echo "<td>" . htmlspecialchars($row['descripcion']) . "</td>";
        echo "<td>" . htmlspecialchars($row['precio']) . "</td>";
        echo "<td>" . htmlspecialchars($row['stock']) . "</td>";
        echo "<td>" . htmlspecialchars($row['categoria']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

function exportar_servicios($conn) {
    $sql = "SELECT nombre, descripcion, precio, duracion FROM servicios ORDER BY id DESC";
    $result = $conn->query($sql);
    echo "<table border='1'>";
    echo "<tr><th>Nombre</th><th>Descripción</th><th>Precio</th><th>Duración (minutos)</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
        echo "<td>" . htmlspecialchars($row['descripcion']) . "</td>";
        echo "<td>" . htmlspecialchars($row['precio']) . "</td>";
        echo "<td>" . htmlspecialchars($row['duracion']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

switch ($tabla) {
    case 'clientes':
        exportar_clientes($conn);
        break;
    case 'mascotas':
        exportar_mascotas($conn);
        break;
    case 'productos':
        exportar_productos($conn);
        break;
    case 'servicios':
        exportar_servicios($conn);
        break;
    default:
        echo "<b>No se especificó una tabla válida.</b>";
        break;
}
?>
