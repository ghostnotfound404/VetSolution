<?php
include('../includes/config.php');

// Obtener estadísticas de exámenes
$sql_stats = "SELECT 
    COUNT(*) as total_examenes,
    COALESCE(SUM(CASE WHEN pagado = 1 THEN 1 ELSE 0 END), 0) as examenes_pagados,
    COALESCE(SUM(CASE WHEN pagado = 0 THEN 1 ELSE 0 END), 0) as examenes_pendientes,
    COALESCE(SUM(CASE WHEN lectura_realizada = 1 THEN 1 ELSE 0 END), 0) as lecturas_realizadas,
    COALESCE(SUM(costo), 0) as costo_total,
    COALESCE(SUM(CASE WHEN pagado = 1 THEN costo ELSE 0 END), 0) as ingresos_confirmados
FROM examenes_laboratorio";
$stats_result = $conn->query($sql_stats);
$stats = $stats_result->fetch_assoc();

// Asegurar que las estadísticas tengan valores por defecto
$stats['total_examenes'] = $stats['total_examenes'] ?? 0;
$stats['examenes_pagados'] = $stats['examenes_pagados'] ?? 0;
$stats['examenes_pendientes'] = $stats['examenes_pendientes'] ?? 0;
$stats['lecturas_realizadas'] = $stats['lecturas_realizadas'] ?? 0;
$stats['costo_total'] = $stats['costo_total'] ?? 0;
$stats['ingresos_confirmados'] = $stats['ingresos_confirmados'] ?? 0;

// Procesar formulario de nuevo examen
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_mascota = intval($_POST['id_mascota']);
    $laboratorio = strtoupper(trim($_POST['laboratorio']));
    $tipo_analisis = trim($_POST['tipo_analisis']);
    $costo = floatval($_POST['costo']);
    $pagado = isset($_POST['pagado']) ? 1 : 0;
    $fecha_envio = $_POST['fecha_envio'];
    $lectura_realizada = isset($_POST['lectura_realizada']) ? 1 : 0;

    // Validaciones
    $errores = [];
    if ($id_mascota <= 0) $errores[] = "Debe seleccionar una mascota válida";
    if (empty($laboratorio)) $errores[] = "El laboratorio es obligatorio";
    if (empty($tipo_analisis)) $errores[] = "El tipo de análisis es obligatorio";
    if ($costo <= 0) $errores[] = "El costo debe ser mayor a 0";
    if (empty($fecha_envio)) $errores[] = "La fecha de envío es obligatoria";

    if (empty($errores)) {
        $sql_insert = "INSERT INTO examenes_laboratorio (id_mascota, laboratorio, tipo_analisis, costo, pagado, fecha_envio, lectura_realizada) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("isssdsi", $id_mascota, $laboratorio, $tipo_analisis, $costo, $pagado, $fecha_envio, $lectura_realizada);
        
        if ($stmt_insert->execute()) {
            echo json_encode(['success' => true, 'message' => 'Examen registrado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $conn->error]);
        }
        $stmt_insert->close();
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => implode('\n', $errores)]);
        exit;
    }
}

// Obtener todas las mascotas para el buscador
$sql_mascotas = "SELECT m.id_mascota, m.nombre, c.nombre as nombre_cliente, c.apellido as apellido_cliente 
                FROM mascotas m 
                JOIN clientes c ON m.id_cliente = c.id_cliente 
                ORDER BY c.nombre, c.apellido, m.nombre";
$mascotas = $conn->query($sql_mascotas);

// Obtener exámenes existentes (con búsqueda si se especifica)
if (isset($_GET['buscar_termino']) && !empty(trim($_GET['buscar_termino']))) {
    $buscar_termino = trim($_GET['buscar_termino']);
    
    $sql_examenes = "SELECT el.*, m.nombre as nombre_mascota, c.nombre as nombre_cliente, c.apellido as apellido_cliente
                    FROM examenes_laboratorio el
                    JOIN mascotas m ON el.id_mascota = m.id_mascota
                    JOIN clientes c ON m.id_cliente = c.id_cliente
                    WHERE m.nombre LIKE ? 
                       OR c.nombre LIKE ? 
                       OR c.apellido LIKE ?
                       OR el.laboratorio LIKE ?
                       OR el.tipo_analisis LIKE ?
                       OR CONCAT(c.nombre, ' ', c.apellido) LIKE ?
                    ORDER BY el.fecha_creacion DESC";
    
    $stmt = $conn->prepare($sql_examenes);
    $searchTerm = "%$buscar_termino%";
    $stmt->bind_param("ssssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $examenes = $stmt->get_result();
    $stmt->close();
} else {
    $sql_examenes = "SELECT el.*, m.nombre as nombre_mascota, c.nombre as nombre_cliente, c.apellido as apellido_cliente
                    FROM examenes_laboratorio el
                    JOIN mascotas m ON el.id_mascota = m.id_mascota
                    JOIN clientes c ON m.id_cliente = c.id_cliente
                    ORDER BY el.fecha_creacion DESC";
    $examenes = $conn->query($sql_examenes);
}
?>

<div class="container-fluid px-4">
    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-vial me-2"></i>Gestión de Exámenes de Laboratorio</h2>
    </div>

    <!-- Tarjeta de Resumen -->
    <div class="row mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Total Exámenes</h6>
                            <h4 class="mb-0"><?php echo $stats['total_examenes']; ?></h4>
                        </div>
                        <i class="fas fa-vial fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card bg-info text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Lecturas Realizadas</h6>
                            <h4 class="mb-0"><?php echo $stats['lecturas_realizadas']; ?></h4>
                        </div>
                        <i class="fas fa-eye fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Exámenes Pagados</h6>
                            <h4 class="mb-0"><?php echo $stats['examenes_pagados']; ?></h4>
                        </div>
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjeta de Búsqueda -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-search me-2"></i>Buscar Exámenes</h5>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" id="formBuscarExamen" class="row g-3 align-items-center">
                <div class="col-md-8">
                    <div class="input-group">
                        <input type="text" class="form-control" id="buscar_termino" name="buscar_termino" 
                               placeholder="Buscar por mascota, propietario o tipo de análisis..."
                               value="<?php echo isset($_GET['buscar_termino']) ? htmlspecialchars($_GET['buscar_termino']) : ''; ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Buscar
                        </button>
                        <?php if (isset($_GET['buscar_termino']) && !empty($_GET['buscar_termino'])): ?>
                            <button type="button" class="btn btn-outline-secondary" onclick="limpiarBusqueda()">
                                <i class="fas fa-times me-1"></i> Limpiar
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-grid">
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#nuevoExamenModal">
                            <i class="fas fa-plus-circle me-1"></i> Nuevo Examen
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Exámenes -->
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Todos los Exámenes</h5>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-success" onclick="descargarExcel()">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if ($examenes->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th class="d-none d-lg-table-cell">Mascota / Propietario</th>
                                <th width="100%" class="d-table-cell d-lg-none">Información del Examen</th>
                                <th class="d-none d-md-table-cell">Laboratorio</th>
                                <th class="d-none d-xl-table-cell">Tipo de Análisis</th>
                                <th class="d-none d-md-table-cell">Costo</th>
                                <th class="d-none d-lg-table-cell">Estado Pago</th>
                                <th class="d-none d-xl-table-cell">Fecha Envío</th>
                                <th class="d-none d-lg-table-cell">Lectura</th>
                                <th width="120" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($examen = $examenes->fetch_assoc()): ?>
                                <tr>
                                    <!-- Vista Desktop - Mascota/Propietario -->
                                    <td class="d-none d-lg-table-cell">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="bg-light rounded-circle p-2 text-center" style="width: 40px; height: 40px;">
                                                    <i class="fas fa-paw text-primary"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($examen['nombre_mascota']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($examen['nombre_cliente'] . ' ' . $examen['apellido_cliente']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Vista Mobile - Información Completa -->
                                    <td class="d-table-cell d-lg-none">
                                        <div class="card border-0 bg-light">
                                            <div class="card-body p-3">
                                                <div class="d-flex align-items-start mb-3">
                                                    <div class="flex-shrink-0 me-3">
                                                        <div class="bg-white rounded-circle p-2 text-center" style="width: 45px; height: 45px;">
                                                            <i class="fas fa-vial text-info" style="font-size: 16px;"></i>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1 fw-bold text-primary"><?php echo htmlspecialchars($examen['nombre_mascota']); ?></h6>
                                                        <p class="mb-2 text-muted small"><?php echo htmlspecialchars($examen['nombre_cliente'] . ' ' . $examen['apellido_cliente']); ?></p>
                                                        
                                                        <!-- Información principal -->
                                                        <div class="row g-2 mb-2">
                                                            <div class="col-6">
                                                                <div class="bg-white rounded p-2">
                                                                    <small class="text-muted d-block">Laboratorio:</small>
                                                                    <strong class="text-dark"><?php echo htmlspecialchars($examen['laboratorio']); ?></strong>
                                                                </div>
                                                            </div>
                                                            <div class="col-6">
                                                                <div class="bg-white rounded p-2">
                                                                    <small class="text-muted d-block">Costo:</small>
                                                                    <strong class="text-success">S/ <?php echo number_format($examen['costo'], 2); ?></strong>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Análisis -->
                                                        <div class="bg-white rounded p-2 mb-2">
                                                            <small class="text-muted d-block">Tipo de Análisis:</small>
                                                            <span class="text-dark"><?php echo htmlspecialchars($examen['tipo_analisis']); ?></span>
                                                        </div>
                                                        
                                                        <!-- Estados y fecha -->
                                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                            <div class="d-flex gap-2 flex-wrap">
                                                                <?php if ($examen['pagado']): ?>
                                                                    <span class="badge bg-success"><i class="fas fa-check me-1"></i>Pagado</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-warning"><i class="fas fa-clock me-1"></i>Pendiente</span>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($examen['lectura_realizada']): ?>
                                                                    <span class="badge bg-info"><i class="fas fa-eye me-1"></i>Leído</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-secondary"><i class="fas fa-hourglass-half me-1"></i>Sin leer</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <small class="text-muted">
                                                                <i class="fas fa-calendar me-1"></i>
                                                                <?php echo date('d/m/Y', strtotime($examen['fecha_envio'])); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($examen['laboratorio']); ?></td>
                                    <td class="d-none d-xl-table-cell"><?php echo htmlspecialchars($examen['tipo_analisis']); ?></td>
                                    <td class="d-none d-md-table-cell fw-bold text-success">S/ <?php echo number_format($examen['costo'], 2); ?></td>
                                    <td class="d-none d-lg-table-cell">
                                        <?php if ($examen['pagado']): ?>
                                            <span class="badge bg-success">Pagado</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pendiente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="d-none d-xl-table-cell"><?php echo date('d/m/Y', strtotime($examen['fecha_envio'])); ?></td>
                                    <td class="d-none d-lg-table-cell">
                                        <?php if ($examen['lectura_realizada']): ?>
                                            <span class="badge bg-info">Realizada</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pendiente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <!-- Botones Desktop -->
                                        <div class="btn-group btn-group-sm d-none d-md-flex" role="group">
                                            <button class="btn btn-outline-primary" onclick="editarExamen(<?php echo $examen['id_examen_lab']; ?>)" 
                                                    data-bs-toggle="tooltip" title="Editar examen">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="eliminarExamen(<?php echo $examen['id_examen_lab']; ?>)" 
                                                    data-bs-toggle="tooltip" title="Eliminar examen">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <button class="btn btn-outline-info" onclick="verDetallesExamen(<?php echo $examen['id_examen_lab']; ?>)" 
                                                    data-bs-toggle="tooltip" title="Ver detalles">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        
                                        <!-- Botones Mobile -->
                                        <div class="d-flex d-md-none flex-column gap-1">
                                            <button class="btn btn-sm btn-outline-primary w-100" onclick="editarExamen(<?php echo $examen['id_examen_lab']; ?>)">
                                                <i class="fas fa-edit me-1"></i> Editar
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger w-100" onclick="eliminarExamen(<?php echo $examen['id_examen_lab']; ?>)">
                                                <i class="fas fa-trash me-1"></i> Eliminar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-vial fa-4x text-muted mb-3"></i>
                    <h4>No hay exámenes registrados</h4>
                    <p class="text-muted">Comienza registrando un nuevo examen de laboratorio</p>
                    <button type="button" class="btn btn-success mt-3" data-bs-toggle="modal" data-bs-target="#nuevoExamenModal">
                        <i class="fas fa-plus-circle me-1"></i> Registrar Examen
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal para Editar Examen -->
<div class="modal fade" id="editarExamenModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title d-flex align-items-center">
                    <i class="fas fa-edit me-2"></i>
                    <span class="d-none d-sm-inline">Editar Examen</span>
                    <span class="d-inline d-sm-none">Editar</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 p-md-4" id="contenidoModalEditar">
                <!-- El contenido se carga dinámicamente -->
                <div class="text-center p-5">
                    <div class="d-flex flex-column align-items-center">
                        <div class="spinner-border text-warning mb-3" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <h5 class="text-muted">Cargando información del examen...</h5>
                        <p class="text-muted small mb-0">Por favor, espera un momento</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Nuevo Examen -->
<div class="modal fade" id="nuevoExamenModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title d-flex align-items-center">
                    <i class="fas fa-vial me-2"></i>
                    <span class="d-none d-sm-inline">Registrar Nuevo Examen</span>
                    <span class="d-inline d-sm-none">Nuevo Examen</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 p-md-4">
                <form id="formNuevoExamen">
                    <!-- Selección de Mascota -->
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label for="mascota_search" class="form-label fw-semibold">
                                <i class="fas fa-paw text-primary me-1"></i>
                                Buscar y Seleccionar Mascota <span class="text-danger">*</span>
                            </label>
                            <div class="position-relative">
                                <div class="input-group">
                                    <span class="input-group-text bg-light d-none d-sm-flex">
                                        <i class="fas fa-search text-muted"></i>
                                    </span>
                                    <input type="text" class="form-control" id="mascota_search" 
                                           placeholder="Escriba el nombre de la mascota o del propietario..." autocomplete="off">
                                    <input type="hidden" id="mascota_select" name="id_mascota" required>
                                </div>
                                <div id="mascota_results" class="dropdown-menu w-100" style="max-height: 200px; overflow-y: auto;"></div>
                            </div>
                            <div class="form-text text-muted small">
                                <i class="fas fa-info-circle me-1"></i>
                                Escriba al menos 2 caracteres para buscar
                            </div>
                        </div>
                    </div>

                    <!-- Información del Laboratorio -->
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-sm-6">
                            <label for="laboratorio" class="form-label fw-semibold">
                                <i class="fas fa-flask text-info me-1"></i>
                                Laboratorio <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light d-none d-sm-flex">
                                    <i class="fas fa-building"></i>
                                </span>
                                <input type="text" class="form-control" id="laboratorio" name="laboratorio" required 
                                       placeholder="Nombre del laboratorio" style="text-transform: uppercase;">
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="tipo_analisis" class="form-label fw-semibold">
                                <i class="fas fa-microscope text-warning me-1"></i>
                                Tipo de Análisis <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light d-none d-sm-flex">
                                    <i class="fas fa-vial"></i>
                                </span>
                                <input type="text" class="form-control" id="tipo_analisis" name="tipo_analisis" required 
                                       placeholder="Ej: Hemograma completo...">
                            </div>
                        </div>
                    </div>

                    <!-- Costo y Fecha -->
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-sm-6">
                            <label for="costo" class="form-label fw-semibold">
                                <i class="fas fa-dollar-sign text-success me-1"></i>
                                Costo (S/) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">S/.</span>
                                <input type="number" step="0.01" class="form-control" id="costo" name="costo" required min="0" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="fecha_envio" class="form-label fw-semibold">
                                <i class="fas fa-calendar text-primary me-1"></i>
                                Fecha de Envío <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light d-none d-sm-flex">
                                    <i class="fas fa-calendar-alt"></i>
                                </span>
                                <input type="date" class="form-control" id="fecha_envio" name="fecha_envio" required>
                            </div>
                        </div>
                    </div>

                    <!-- Estados -->
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <div class="card bg-light border-0">
                                <div class="card-body p-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="pagado" name="pagado">
                                        <label class="form-check-label fw-semibold" for="pagado">
                                            <i class="fas fa-money-bill-wave text-success me-1"></i> 
                                            <span class="d-none d-sm-inline">¿Está pagado?</span>
                                            <span class="d-inline d-sm-none">Pagado</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <div class="card bg-light border-0">
                                <div class="card-body p-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="lectura_realizada" name="lectura_realizada">
                                        <label class="form-check-label fw-semibold" for="lectura_realizada">
                                            <i class="fas fa-eye text-info me-1"></i> 
                                            <span class="d-none d-sm-inline">¿Se realizó la lectura?</span>
                                            <span class="d-inline d-sm-none">Lectura realizada</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Información adicional -->
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="alert alert-light border d-flex align-items-center mb-0">
                                <i class="fas fa-lightbulb text-warning me-2"></i>
                                <div class="flex-grow-1">
                                    <small class="mb-0 text-muted">
                                        <strong>Tip:</strong> Puede marcar como pagado y con lectura realizada si ya se completaron estos procesos.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer flex-column flex-sm-row p-3">
                <button type="button" class="btn btn-secondary w-100 w-sm-auto mb-2 mb-sm-0 me-sm-2" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancelar
                </button>
                <button type="submit" form="formNuevoExamen" class="btn btn-success w-100 w-sm-auto">
                    <i class="fas fa-save me-1"></i> 
                    <span class="d-none d-sm-inline">Guardar Examen</span>
                    <span class="d-inline d-sm-none">Guardar</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Inicializar tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Convertir laboratorio a mayúsculas automáticamente
    $('#laboratorio').on('input', function() {
        this.value = this.value.toUpperCase();
    });

    // Establecer fecha actual por defecto
    $('#fecha_envio').val(new Date().toISOString().split('T')[0]);

    // Buscador de mascotas
    $('#mascota_search').on('input', function() {
        const termino = $(this).val();
        const resultsDiv = $('#mascota_results');
        
        if (termino.length >= 2) {
            $.ajax({
                url: 'modules/buscar_mascotas.php',
                method: 'GET',
                data: { termino: termino },
                dataType: 'json',
                success: function(mascotas) {
                    resultsDiv.empty().show();
                    
                    if (mascotas.length > 0) {
                        mascotas.forEach(function(mascota) {
                            const item = $(`
                                <div class="dropdown-item cursor-pointer p-3" data-id="${mascota.id_mascota}">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0 me-3">
                                            <div class="bg-primary rounded-circle text-white d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                                                <i class="fas fa-paw"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0">${mascota.nombre}</h6>
                                            <small class="text-muted">${mascota.nombre_cliente} ${mascota.apellido_cliente}</small>
                                        </div>
                                    </div>
                                </div>
                            `);
                            
                            item.click(function() {
                                $('#mascota_search').val(mascota.nombre + ' - ' + mascota.nombre_cliente + ' ' + mascota.apellido_cliente);
                                $('#mascota_select').val(mascota.id_mascota);
                                resultsDiv.hide();
                            });
                            
                            resultsDiv.append(item);
                        });
                    } else {
                        resultsDiv.html('<div class="dropdown-item text-muted p-3"><i class="fas fa-search me-2"></i>No se encontraron mascotas</div>');
                    }
                },
                error: function() {
                    resultsDiv.html('<div class="dropdown-item text-danger p-3"><i class="fas fa-exclamation-triangle me-2"></i>Error al buscar mascotas</div>');
                }
            });
        } else {
            resultsDiv.hide();
            $('#mascota_select').val('');
        }
    });

    // Ocultar resultados al hacer clic fuera
    $(document).click(function(e) {
        if (!$(e.target).closest('#mascota_search, #mascota_results').length) {
            $('#mascota_results').hide();
        }
    });

    // Manejar envío del formulario
    $('#formNuevoExamen').on('submit', function(e) {
        e.preventDefault();
        
        if ($(this).data('submitting')) {
            return false;
        }
        $(this).data('submitting', true);
        
        const formData = new FormData(this);
        
        $.ajax({
            url: 'modules/examenes.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                $('#formNuevoExamen').data('submitting', false);
                if (response.success) {
                    $('#nuevoExamenModal').modal('hide');
                    $('#formNuevoExamen')[0].reset();
                    $('#mascota_search').val('');
                    $('#mascota_select').val('');
                    
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: response.message,
                        confirmButtonColor: '#198754',
                        customClass: {
                            popup: 'swal-responsive'
                        }
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message,
                        confirmButtonColor: '#dc3545',
                        customClass: {
                            popup: 'swal-responsive'
                        }
                    });
                }
            },
            error: function() {
                $('#formNuevoExamen').data('submitting', false);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al guardar el examen',
                    confirmButtonColor: '#dc3545',
                    customClass: {
                        popup: 'swal-responsive'
                    }
                });
            }
        });
    });
    
    // Ajustar modal según el tamaño de pantalla
    function adjustModalForScreen() {
        const isMobile = window.innerWidth < 768;
        const modalDialogs = document.querySelectorAll('.modal-dialog');
        
        modalDialogs.forEach(modal => {
            if (isMobile) {
                modal.classList.add('modal-fullscreen-sm-down');
            } else {
                modal.classList.remove('modal-fullscreen-sm-down');
            }
        });
    }
    
    // Ejecutar al cargar y redimensionar
    adjustModalForScreen();
    window.addEventListener('resize', adjustModalForScreen);
    
    // Mejorar accesibilidad en dispositivos táctiles
    if ('ontouchstart' in window) {
        // Añadir clase para dispositivos táctiles
        document.body.classList.add('touch-device');
        
        // Mejorar interacción con botones pequeños
        $('.btn-sm').addClass('touch-friendly');
    }
});

// Función para ver detalles del examen
function verDetallesExamen(id) {
    // Esta función puede implementarse para mostrar detalles adicionales
    Swal.fire({
        title: 'Detalles del Examen',
        text: 'Funcionalidad en desarrollo',
        icon: 'info',
        confirmButtonColor: '#0d6efd',
        customClass: {
            popup: 'swal-responsive'
        }
    });
}

function limpiarBusqueda() {
    window.location.href = 'index.php#examenes';
}

function descargarExcel() {
    const termino = $('#buscar_termino').val();
    window.open('exportar_excel.php?tipo=examenes&termino=' + encodeURIComponent(termino), '_blank');
}

function descargarPDF() {
    const termino = $('#buscar_termino').val();
    window.open('exportar_pdf.php?tipo=examenes&termino=' + encodeURIComponent(termino), '_blank');
}

function editarExamen(id) {
    // Resetear el contenido del modal con loading
    $('#contenidoModalEditar').html(`
        <div class="text-center p-5">
            <div class="d-flex flex-column align-items-center">
                <div class="spinner-border text-warning mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <h5 class="text-muted">Cargando información del examen...</h5>
                <p class="text-muted small mb-0">Por favor, espera un momento</p>
            </div>
        </div>
    `);
    
    // Mostrar el modal inmediatamente
    $('#editarExamenModal').modal('show');
    
    $.ajax({
        url: 'modules/editar_examen.php?id=' + id,
        method: 'GET',
        timeout: 10000, // 10 segundos de timeout
        success: function(response) {
            $('#contenidoModalEditar').html(response);
        },
        error: function(xhr, status, error) {
            let errorMessage = 'No se pudo cargar la información del examen';
            
            if (status === 'timeout') {
                errorMessage = 'La solicitud tardó demasiado tiempo. Verifica tu conexión.';
            } else if (xhr.status === 404) {
                errorMessage = 'Examen no encontrado.';
            } else if (xhr.status === 500) {
                errorMessage = 'Error interno del servidor.';
            }
            
            $('#contenidoModalEditar').html(`
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i> Error al cargar
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-5">
                    <div class="d-flex flex-column align-items-center">
                        <i class="fas fa-exclamation-circle fa-4x text-danger mb-3"></i>
                        <h5 class="text-danger mb-3">¡Ups! Algo salió mal</h5>
                        <p class="text-muted mb-4">${errorMessage}</p>
                        <div class="d-flex gap-2 flex-column flex-sm-row">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i> Cerrar
                            </button>
                            <button type="button" class="btn btn-primary" onclick="editarExamen(${id})">
                                <i class="fas fa-refresh me-1"></i> Reintentar
                            </button>
                        </div>
                    </div>
                </div>
            `);
        }
    });
}

function eliminarExamen(id) {
    Swal.fire({
        title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i>¿Eliminar examen?',
        html: `
            <div class="text-start">
                <p class="mb-2">Estás a punto de eliminar este examen de laboratorio.</p>
                <div class="card bg-light border-warning">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-vial me-3 text-info fa-2x"></i>
                            <div>
                                <h6 class="card-title mb-1">Examen de Laboratorio</h6>
                                <small class="text-muted">ID: #${id}</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="alert alert-warning mt-3 mb-0 d-flex align-items-center">
                    <i class="fas fa-info-circle me-2"></i>
                    <small><strong>Advertencia:</strong> Esta acción eliminará todo el historial del examen y no se puede deshacer.</small>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-trash me-1"></i> Sí, eliminar',
        cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
        reverseButtons: true,
        customClass: {
            popup: 'swal-responsive',
            title: 'swal-title-responsive',
            htmlContainer: 'swal-html-responsive',
            confirmButton: 'swal-btn-responsive',
            cancelButton: 'swal-btn-responsive'
        },
        backdrop: true,
        allowOutsideClick: false,
        allowEscapeKey: true,
        allowEnterKey: false,
        showClass: {
            popup: 'animate__animated animate__fadeInDown animate__faster'
        },
        hideClass: {
            popup: 'animate__animated animate__fadeOutUp animate__faster'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Mostrar loading
            Swal.fire({
                title: 'Eliminando examen...',
                html: '<div class="d-flex justify-content-center"><div class="spinner-border text-danger" role="status"><span class="visually-hidden">Cargando...</span></div></div>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                customClass: {
                    popup: 'swal-responsive'
                }
            });
            
            $.ajax({
                url: 'modules/eliminar_examen.php',
                method: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: '¡Eliminado!',
                            html: `
                                <div class="text-center">
                                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                    <p class="mb-0">El examen ha sido eliminado correctamente.</p>
                                </div>
                            `,
                            icon: 'success',
                            confirmButtonColor: '#198754',
                            confirmButtonText: '<i class="fas fa-check me-1"></i> Entendido',
                            customClass: {
                                popup: 'swal-responsive'
                            }
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error al eliminar',
                            html: `
                                <div class="text-center">
                                    <i class="fas fa-exclamation-circle fa-3x text-danger mb-3"></i>
                                    <p class="mb-0">${response.message || 'No se pudo eliminar el examen'}</p>
                                </div>
                            `,
                            icon: 'error',
                            confirmButtonColor: '#dc3545',
                            confirmButtonText: '<i class="fas fa-times me-1"></i> Cerrar',
                            customClass: {
                                popup: 'swal-responsive'
                            }
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        title: 'Error de conexión',
                        html: `
                            <div class="text-center">
                                <i class="fas fa-wifi fa-3x text-danger mb-3"></i>
                                <p class="mb-0">No se pudo completar la solicitud. Verifica tu conexión.</p>
                            </div>
                        `,
                        icon: 'error',
                        confirmButtonColor: '#dc3545',
                        confirmButtonText: '<i class="fas fa-refresh me-1"></i> Reintentar',
                        customClass: {
                            popup: 'swal-responsive'
                        }
                    });
                }
            });
        }
    });
}
</script>

<!-- Estilos CSS para mejorar la responsividad -->
<style>
/* Estilos para SweetAlert responsivo */
.swal-responsive {
    font-size: 14px !important;
}

@media (max-width: 576px) {
    .swal-responsive {
        width: 95% !important;
        margin: 10px !important;
        font-size: 13px !important;
    }
    
    .swal-title-responsive {
        font-size: 18px !important;
        line-height: 1.3 !important;
    }
    
    .swal-html-responsive {
        font-size: 13px !important;
    }
    
    .swal-btn-responsive {
        padding: 8px 16px !important;
        font-size: 13px !important;
        min-width: 100px !important;
    }
}

/* Mejoras para dispositivos táctiles */
.touch-device .btn-sm.touch-friendly {
    min-height: 40px;
    min-width: 40px;
    padding: 8px 12px;
}

.touch-device .table-responsive {
    -webkit-overflow-scrolling: touch;
}

/* Mejoras para modales en móviles */
@media (max-width: 767px) {
    .modal-dialog {
        margin: 10px;
        width: calc(100% - 20px);
    }
    
    .modal-fullscreen-sm-down {
        width: 100vw;
        max-width: none;
        height: 100vh;
        margin: 0;
    }
    
    .modal-fullscreen-sm-down .modal-content {
        height: 100vh;
        border: 0;
        border-radius: 0;
    }
    
    .modal-fullscreen-sm-down .modal-header {
        border-radius: 0;
    }
    
    .modal-fullscreen-sm-down .modal-body {
        overflow-y: auto;
        max-height: calc(100vh - 140px);
    }
}

/* Mejoras para la tabla en móviles */
@media (max-width: 576px) {
    .table-responsive {
        border: none;
    }
    
    .table td {
        border: none;
        padding: 0.5rem 0.25rem;
    }
    
    /* Mejorar la vista mobile de exámenes */
    .d-table-cell.d-lg-none .card {
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: all 0.2s ease;
        border-radius: 8px;
    }
    
    .d-table-cell.d-lg-none .card:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        transform: translateY(-1px);
    }
}

/* Mejoras específicas para exámenes en mobile */
@media (max-width: 991px) {
    /* Cards de información en mobile */
    .d-table-cell.d-lg-none .bg-white {
        border: 1px solid #e9ecef;
    }
    
    /* Badges más legibles en mobile */
    .d-table-cell.d-lg-none .badge {
        font-size: 0.65em;
        padding: 0.3em 0.6em;
    }
}

/* Mejoras para el dropdown de mascotas */
.dropdown-menu {
    max-height: 250px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.dropdown-item {
    cursor: pointer;
    transition: all 0.2s ease;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
    transform: translateX(2px);
}

/* Mejoras para el scroll horizontal en tablets */
@media (min-width: 577px) and (max-width: 991px) {
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
}

/* Ajustes para botones en la tabla */
@media (max-width: 767px) {
    .btn-group .btn-sm {
        margin-bottom: 2px;
        border-radius: 4px !important;
    }
}

/* Mejoras para formularios en móviles */
@media (max-width: 576px) {
    .input-group-text {
        min-width: auto;
        padding: 0.375rem 0.5rem;
    }
    
    .form-label {
        font-size: 14px;
        margin-bottom: 0.25rem;
    }
    
    .form-control {
        font-size: 16px; /* Evita zoom en iOS */
    }
    
    .btn {
        padding: 0.5rem 1rem;
        font-size: 14px;
    }
    
    .modal-footer {
        padding: 1rem;
    }
    
    .modal-footer .btn {
        margin-bottom: 0.5rem;
    }
    
    /* Switch forms más grandes en mobile */
    .form-check-input {
        width: 2rem;
        height: 1rem;
    }
    
    .form-check-label {
        font-size: 14px;
        padding-left: 0.5rem;
    }
}

/* Estados específicos para exámenes */
.badge.bg-success {
    background-color: #198754 !important;
}

.badge.bg-warning {
    background-color: #ffc107 !important;
    color: #000 !important;
}

.badge.bg-info {
    background-color: #0dcaf0 !important;
    color: #000 !important;
}

.badge.bg-secondary {
    background-color: #6c757d !important;
}

/* Animaciones suaves */
.card {
    transition: all 0.2s ease;
}

.btn {
    transition: all 0.2s ease;
}

.modal.fade .modal-dialog {
    transition: transform 0.2s ease-out;
}

/* Mejoras para la accesibilidad */
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

/* Loading states */
.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}

/* Focus states para accesibilidad */
.btn:focus,
.form-control:focus {
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    border-color: #80bdff;
}

/* Alto contraste para texto importante */
.fw-bold {
    font-weight: 600 !important;
}

/* Mejoras específicas para cards de información */
.card-body .row.g-2 .col-6 .bg-white {
    min-height: 60px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

/* Responsividad para iconos */
@media (max-width: 576px) {
    .fa-2x {
        font-size: 1.5em !important;
    }
    
    .fa-3x {
        font-size: 2em !important;
    }
    
    .fa-4x {
        font-size: 2.5em !important;
    }
}

/* Mejoras para el input de fecha en mobile */
@media (max-width: 576px) {
    input[type="date"] {
        font-size: 16px;
        padding: 0.5rem 0.75rem;
    }
}

/* Estados hover mejorados */
.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.025);
}

/* Mejoras para los badges en la vista mobile */
@media (max-width: 991px) {
    .d-table-cell.d-lg-none .badge {
        margin: 0 2px;
        white-space: nowrap;
    }
}
</style>
