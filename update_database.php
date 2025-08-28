<?php
include('includes/config.php');

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

$result = [
    'success' => [],
    'error' => []
];

// Verificar si la columna 'tipo' ya existe en la tabla productos
$check_sql = "SHOW COLUMNS FROM productos LIKE 'tipo'";
$check_result = $conn->query($check_sql);

if ($check_result && $check_result->num_rows === 0) {
    // La columna no existe, añadirla
    $alter_sql = "ALTER TABLE productos ADD COLUMN tipo VARCHAR(20) NOT NULL DEFAULT 'clinica'";
    if ($conn->query($alter_sql) === TRUE) {
        $result['success'][] = "✅ Columna 'tipo' añadida a la tabla 'productos'";
    } else {
        $result['error'][] = "❌ Error al añadir columna 'tipo' a productos: " . $conn->error;
    }
} else {
    $result['success'][] = "ℹ️ La columna 'tipo' ya existe en la tabla 'productos'";
}

// Verificar si la columna 'tipo' ya existe en la tabla servicios
$check_sql = "SHOW COLUMNS FROM servicios LIKE 'tipo'";
$check_result = $conn->query($check_sql);

if ($check_result && $check_result->num_rows === 0) {
    // La columna no existe, añadirla
    $alter_sql = "ALTER TABLE servicios ADD COLUMN tipo VARCHAR(20) NOT NULL DEFAULT 'clinica'";
    if ($conn->query($alter_sql) === TRUE) {
        $result['success'][] = "✅ Columna 'tipo' añadida a la tabla 'servicios'";
    } else {
        $result['error'][] = "❌ Error al añadir columna 'tipo' a servicios: " . $conn->error;
    }
} else {
    $result['success'][] = "ℹ️ La columna 'tipo' ya existe en la tabla 'servicios'";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualización de la Estructura de la Base de Datos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 40px 0;
        }
        .card {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
        }
        .success-message {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
            padding: 8px 12px;
            margin: 5px 0;
            border-radius: 4px;
        }
        .error-message {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
            padding: 8px 12px;
            margin: 5px 0;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-database me-2"></i>Actualización de la Base de Datos</h4>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title mb-4">Resultados de la actualización</h5>
                        
                        <?php if (count($result['success']) > 0): ?>
                        <h6><i class="fas fa-check-circle me-2 text-success"></i>Operaciones exitosas:</h6>
                        <div class="mb-4">
                            <?php foreach ($result['success'] as $message): ?>
                                <div class="success-message"><?= $message ?></div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (count($result['error']) > 0): ?>
                        <h6><i class="fas fa-exclamation-triangle me-2 text-danger"></i>Errores:</h6>
                        <div class="mb-4">
                            <?php foreach ($result['error'] as $message): ?>
                                <div class="error-message"><?= $message ?></div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info">
                            <p class="mb-0"><i class="fas fa-info-circle me-2"></i>La estructura de la base de datos ha sido actualizada para soportar el campo 'tipo' en productos y servicios.</p>
                        </div>
                        
                        <div class="mt-4">
                            <a href="index.php" class="btn btn-primary">
                                <i class="fas fa-home me-2"></i>Volver a la página principal
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
