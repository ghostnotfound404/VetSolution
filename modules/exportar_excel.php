<?php
include('../includes/config.php');

// Verificar el tipo de auditoría solicitado
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';

if (empty($tipo)) {
    die('Tipo de auditoría no especificado');
}

// Configurar headers para descarga de Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="auditoria_clientes_' . $tipo . '_' . date('Y-m-d') . '.xls"');
header('Cache-Control: max-age=0');

// Obtener datos según el tipo de auditoría
switch ($tipo) {
    case 'clientes_general':
        $sql = "SELECT * FROM clientes ORDER BY nombre, apellido";
        $titulo = "Reporte General de Clientes";
        break;
    case 'clientes_activos':
        $sql = "SELECT DISTINCT c.* FROM clientes c 
                INNER JOIN mascotas m ON c.id_cliente = m.id_cliente 
                ORDER BY c.nombre, c.apellido";
        $titulo = "Clientes Activos (con mascotas)";
        break;
    case 'clientes_nuevos':
        // Verificar si existe la columna fecha_registro
        $check_column = "SHOW COLUMNS FROM clientes LIKE 'fecha_registro'";
        $column_result = $conn->query($check_column);
        
        if ($column_result->num_rows > 0) {
            $sql = "SELECT * FROM clientes 
                    WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 1 MONTH) 
                    ORDER BY fecha_registro DESC";
        } else {
            // Si no existe la columna, mostrar todos los clientes
            $sql = "SELECT * FROM clientes ORDER BY nombre, apellido";
        }
        $titulo = "Clientes Nuevos (Último Mes)";
        break;
    default:
        die('Tipo de auditoría no válido');
}

$result = $conn->query($sql);

// Generar contenido Excel
echo "<html>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<style>";
echo "table { border-collapse: collapse; width: 100%; }";
echo "th, td { border: 1px solid #000; padding: 8px; text-align: left; }";
echo "th { background-color: #f2f2f2; font-weight: bold; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<h2>$titulo</h2>";
echo "<p>Fecha de generación: " . date('d/m/Y H:i:s') . "</p>";

if ($result->num_rows > 0) {
    echo "<table>";
    echo "<thead>";
    echo "<tr>";
    echo "<th>ID</th>";
    echo "<th>Nombre</th>";
    echo "<th>Apellido</th>";
    echo "<th>DNI</th>";
    echo "<th>Celular</th>";
    echo "<th>Dirección</th>";
    // Verificar si estamos mostrando fecha_registro y si la columna existe
    $check_column = "SHOW COLUMNS FROM clientes LIKE 'fecha_registro'";
    $column_result = $conn->query($check_column);
    $show_fecha = ($tipo == 'clientes_nuevos' && $column_result->num_rows > 0);
    
    if ($show_fecha) {
        echo "<th>Fecha Registro</th>";
    }
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id_cliente']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
        echo "<td>" . htmlspecialchars($row['apellido']) . "</td>";
        echo "<td>" . htmlspecialchars($row['dni']) . "</td>";
        echo "<td>" . htmlspecialchars($row['celular']) . "</td>";
        echo "<td>" . htmlspecialchars($row['direccion']) . "</td>";
        if ($show_fecha && isset($row['fecha_registro'])) {
            echo "<td>" . htmlspecialchars($row['fecha_registro']) . "</td>";
        }
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
    echo "<br>";
    echo "<p>Total de registros: " . $result->num_rows . "</p>";
} else {
    echo "<p>No hay datos para mostrar.</p>";
}

echo "</body>";
echo "</html>";

$conn->close();
?>
