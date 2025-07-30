<?php
// Verificar si se está ejecutando una acción específica
$accion = isset($_GET['accion']) ? $_GET['accion'] : '';
$mensaje = '';
$tipo_mensaje = '';

include('../includes/config.php');

if ($accion === 'instalar') {
    // Proceso de instalación
    $errores = [];
    $exitos = [];
    
    // Crear base de datos
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
    if ($conn->query($sql) === TRUE) {
        $exitos[] = "✅ Base de datos creada correctamente";
    } else {
        $errores[] = "❌ Error al crear base de datos: " . $conn->error;
    }

    // Seleccionar la base de datos
    $conn->select_db(DB_NAME);

    // Crear tablas
    $queries = [
        "clientes" => "CREATE TABLE IF NOT EXISTS clientes (
            id_cliente INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            apellido VARCHAR(255) NOT NULL,
            celular VARCHAR(20) NOT NULL,
            dni VARCHAR(20) NULL,
            direccion VARCHAR(255) NULL
        )",

        "mascotas" => "CREATE TABLE IF NOT EXISTS mascotas (
            id_mascota INT AUTO_INCREMENT PRIMARY KEY,
            id_cliente INT NOT NULL,
            nombre VARCHAR(255) NOT NULL,
            especie VARCHAR(50) NOT NULL,
            raza VARCHAR(100) NOT NULL,
            genero VARCHAR(10) NOT NULL,
            fecha_nacimiento DATE NOT NULL,
            estado VARCHAR(10) NOT NULL,
            FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente)
        )",

        "historia_clinica" => "CREATE TABLE IF NOT EXISTS historia_clinica (
            id_historia INT AUTO_INCREMENT PRIMARY KEY,
            id_mascota INT NOT NULL,
            fecha DATE NOT NULL,
            motivo TEXT,
            anamnesis TEXT,
            peso DECIMAL(5,2),
            temperatura DECIMAL(5,2),
            diagnostico TEXT,
            observaciones TEXT,
            tratamiento TEXT,
            FOREIGN KEY (id_mascota) REFERENCES mascotas(id_mascota)
        )",

        "servicios" => "CREATE TABLE IF NOT EXISTS servicios (
            id_servicio INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            precio DECIMAL(10,2) NOT NULL,
            descripcion TEXT
        )",

        "productos" => "CREATE TABLE IF NOT EXISTS productos (
            id_producto INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            precio DECIMAL(10,2) NOT NULL,
            stock INT NOT NULL,
            descripcion TEXT
        )",

        "ventas" => "CREATE TABLE IF NOT EXISTS ventas (
            id_venta INT AUTO_INCREMENT PRIMARY KEY,
            id_cliente INT NOT NULL,
            fecha DATETIME NOT NULL,
            total DECIMAL(10,2) NOT NULL,
            observaciones TEXT,
            FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente)
        )",

        "hospitalizaciones" => "CREATE TABLE IF NOT EXISTS hospitalizaciones (
            id_hospitalizacion INT AUTO_INCREMENT PRIMARY KEY,
            id_mascota INT NOT NULL,
            hora_inicio DATETIME NOT NULL,
            hora_termino DATETIME NULL,
            motivo TEXT,
            observaciones TEXT,
            FOREIGN KEY (id_mascota) REFERENCES mascotas(id_mascota)
        )"
    ];

    foreach ($queries as $tabla => $query) {
        if ($conn->query($query) === TRUE) {
            $exitos[] = "✅ Tabla '$tabla' creada correctamente";
        } else {
            $errores[] = "❌ Error al crear tabla '$tabla': " . $conn->error;
        }
    }
    
    if (empty($errores)) {
        $mensaje = "🎉 ¡Instalación completada exitosamente! Todas las tablas han sido creadas.";
        $tipo_mensaje = "success";
    } else {
        $mensaje = "⚠️ Instalación completada con algunos errores.";
        $tipo_mensaje = "warning";
    }
    
} elseif ($accion === 'reparar_autoincrement') {
    // Proceso de reparación de AUTO_INCREMENT
    $tables_config = [
        'clientes' => 'id_cliente',
        'mascotas' => 'id_mascota', 
        'servicios' => 'id_servicio',
        'productos' => 'id_producto',
        'ventas' => 'id_venta',
        'hospitalizaciones' => 'id_hospitalizacion',
        'historia_clinica' => 'id_historia'
    ];

    $reparaciones = 0;
    $total_tablas = 0;
    $detalles = [];

    $conn->select_db(DB_NAME);

    foreach($tables_config as $table => $id_field) {
        // Verificar si la tabla existe
        $check_table = $conn->query("SHOW TABLES LIKE '$table'");
        if($check_table->num_rows == 0) {
            $detalles[] = "⚠️ Tabla '$table' no existe";
            continue;
        }
        
        $total_tablas++;
        
        // Obtener el ID más alto actual
        $max_result = $conn->query("SELECT MAX($id_field) as max_id FROM $table");
        
        if($max_result && $max_row = $max_result->fetch_assoc()) {
            $max_id = $max_row['max_id'] ?? 0;
            $next_auto_increment = $max_id + 1;
            
            // Reparar AUTO_INCREMENT
            $alter_sql = "ALTER TABLE $table AUTO_INCREMENT = $next_auto_increment";
            
            if($conn->query($alter_sql)) {
                $detalles[] = "✅ Tabla '$table': AUTO_INCREMENT establecido a $next_auto_increment";
                $reparaciones++;
            } else {
                $detalles[] = "❌ Error en tabla '$table': " . $conn->error;
            }
        }
    }
    
    $mensaje = "🔧 Reparación completada: $reparaciones de $total_tablas tablas procesadas";
    $tipo_mensaje = $reparaciones > 0 ? "success" : "info";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación y Mantenimiento - VetApp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            margin-top: 50px;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border: none;
        }
        .card-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            text-align: center;
            padding: 20px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
        }
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
        }
        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            color: #000;
        }
        .detail-item {
            padding: 8px;
            margin: 4px 0;
            border-radius: 8px;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-database"></i> VetApp - Instalación y Mantenimiento</h2>
                        <p class="mb-0">Sistema de gestión veterinaria</p>
                    </div>
                    <div class="card-body p-4">
                        
                        <?php if (!empty($mensaje)): ?>
                            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                                <strong><?php echo $mensaje; ?></strong>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            
                            <?php if (isset($exitos) && !empty($exitos)): ?>
                                <div class="mt-3">
                                    <h6><i class="fas fa-check-circle text-success"></i> Operaciones exitosas:</h6>
                                    <?php foreach($exitos as $exito): ?>
                                        <div class="detail-item"><?php echo $exito; ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($errores) && !empty($errores)): ?>
                                <div class="mt-3">
                                    <h6><i class="fas fa-exclamation-triangle text-warning"></i> Errores encontrados:</h6>
                                    <?php foreach($errores as $error): ?>
                                        <div class="detail-item text-danger"><?php echo $error; ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($detalles) && !empty($detalles)): ?>
                                <div class="mt-3">
                                    <h6><i class="fas fa-list"></i> Detalles de la operación:</h6>
                                    <?php foreach($detalles as $detalle): ?>
                                        <div class="detail-item"><?php echo $detalle; ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-4 text-center">
                                <a href="install.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Volver
                                </a>
                                <a href="../index.php" class="btn btn-primary">
                                    <i class="fas fa-home"></i> Ir a VetApp
                                </a>
                            </div>
                        <?php else: ?>
                            
                            <div class="text-center mb-4">
                                <i class="fas fa-paw fa-3x text-primary"></i>
                                <h4 class="mt-3">Bienvenido al instalador de VetApp</h4>
                                <p class="text-muted">Utiliza las opciones siguientes para instalar o mantener tu sistema</p>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-download fa-2x text-primary mb-3"></i>
                                            <h5>Instalación Inicial</h5>
                                            <p class="text-muted">Crea la base de datos y todas las tablas necesarias para VetApp</p>
                                            <a href="install.php?accion=instalar" class="btn btn-primary">
                                                <i class="fas fa-play"></i> Instalar Ahora
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-tools fa-2x text-warning mb-3"></i>
                                            <h5>Reparar AUTO_INCREMENT</h5>
                                            <p class="text-muted">Corrige los contadores automáticos después de eliminar registros</p>
                                            <a href="install.php?accion=reparar_autoincrement" class="btn btn-warning">
                                                <i class="fas fa-wrench"></i> Reparar Ahora
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="row">
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-info-circle"></i> Información importante:</h6>
                                        <ul class="mb-0">
                                            <li><strong>Instalación:</strong> Solo necesaria la primera vez o para recrear la base de datos</li>
                                            <li><strong>Reparar AUTO_INCREMENT:</strong> Útil cuando se eliminan registros y quieres mantener la secuencia numérica</li>
                                            <li><strong>Respaldo:</strong> Siempre haz un respaldo antes de ejecutar cualquier operación</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4">
                                <a href="../index.php" class="btn btn-success">
                                    <i class="fas fa-home"></i> Ir a VetApp
                                </a>
                            </div>
                            
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
