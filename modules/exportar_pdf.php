<?php
require_once('../includes/config.php');

// Verificar el tipo de auditoría solicitado
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';

if (empty($tipo)) {
    die('Tipo de auditoría no especificado');
}

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

// Configurar headers para PDF (usando output HTML que se puede convertir a PDF)
header('Content-Type: text/html; charset=UTF-8');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            font-size: 12px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        
        .header h1 {
            color: #333;
            margin: 0;
            font-size: 24px;
        }
        
        .info {
            margin-bottom: 20px;
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        th {
            background-color: #4CAF50;
            color: white;
            font-weight: bold;
        }
        
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        
        .no-data {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 20px;
        }
        
        @media print {
            body { margin: 0; }
            .header { margin-bottom: 20px; }
        }
    </style>
    <script>
        // Auto-print cuando la página carga (opcional)
        window.onload = function() {
            window.print();
        }
    </script>
</head>
<body>
    <div class="header">
        <h1><?php echo $titulo; ?></h1>
        <p>Clínica Veterinaria</p>
    </div>
    
    <div class="info">
        <p><strong>Fecha de generación:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
        <p><strong>Tipo de reporte:</strong> <?php echo ucfirst(str_replace('_', ' ', $tipo)); ?></p>
    </div>

    <?php if ($result->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Apellido</th>
                    <th>DNI</th>
                    <th>Celular</th>
                    <th>Dirección</th>
                    <?php 
                    // Verificar si estamos mostrando fecha_registro y si la columna existe
                    $check_column = "SHOW COLUMNS FROM clientes LIKE 'fecha_registro'";
                    $column_result = $conn->query($check_column);
                    $show_fecha = ($tipo == 'clientes_nuevos' && $column_result->num_rows > 0);
                    
                    if ($show_fecha): ?>
                        <th>Fecha Registro</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['id_cliente']); ?></td>
                    <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($row['apellido']); ?></td>
                    <td><?php echo htmlspecialchars($row['dni']); ?></td>
                    <td><?php echo htmlspecialchars($row['celular']); ?></td>
                    <td><?php echo htmlspecialchars($row['direccion']); ?></td>
                    <?php if ($show_fecha && isset($row['fecha_registro'])): ?>
                        <td><?php echo htmlspecialchars($row['fecha_registro']); ?></td>
                    <?php endif; ?>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <div class="info">
            <p><strong>Total de registros:</strong> <?php echo $result->num_rows; ?></p>
        </div>
    <?php else: ?>
        <div class="no-data">
            <p>No hay datos disponibles para este tipo de auditoría.</p>
        </div>
    <?php endif; ?>
    
    <div class="footer">
        <p>Reporte generado automáticamente por el Sistema de Gestión Veterinaria</p>
        <p>© <?php echo date('Y'); ?> Clínica Veterinaria - Todos los derechos reservados</p>
    </div>
</body>
</html>

<?php
$conn->close();
?>
