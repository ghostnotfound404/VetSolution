<?php
include('../includes/config.php');
include('../includes/pagination.php');

// Inicializar sistema de paginación
$pagination = new PaginationHelper($conn, 10);
$buscar = isset($_GET['buscar_servicio']) ? trim($_GET['buscar_servicio']) : '';

// Procesar formulario de nuevo servicio
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $precio = trim($_POST['precio']);
    $tipo = isset($_POST['tipo']) ? trim($_POST['tipo']) : '';

    // Validaciones
    $errores = [];
    
    if (empty($nombre)) {
        $errores[] = "El nombre del servicio es obligatorio";
    }
    
    if (empty($precio) || $precio <= 0) {
        $errores[] = "El precio debe ser mayor a 0";
    }
    
    if (empty($tipo)) {
        $errores[] = "El tipo de servicio es obligatorio";
    }
    
    // Validar tipo
    $tipos_validos = ['clinica', 'farmacia', 'petshop', 'spa'];
    if (!in_array(strtolower($tipo), $tipos_validos)) {
        $errores[] = "El tipo seleccionado no es válido";
    }

    if (empty($errores)) {
        $insert_sql = "INSERT INTO servicios (nombre, precio, tipo) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("sds", $nombre, $precio, $tipo);
        
        if ($stmt->execute()) {
            echo "<script>
                if (window.parent && window.parent.location.hash) {
                    window.parent.location.reload();
                } else {
                    window.location.href = '../index.php#/servicios';
                }
            </script>";
        } else {
            echo "<script>alert('Error al guardar servicio: " . $conn->error . "');</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('Errores encontrados:\\n" . implode("\\n", $errores) . "');</script>";
    }
}

// Obtener servicios con paginación
if (!empty($buscar)) {
    // Búsqueda con paginación
    $search_fields = ['nombre'];
    $serviciosData = $pagination->searchWithPagination(
        'servicios', 
        $search_fields, 
        $buscar, 
        '*', 
        '', 
        'id_servicio DESC'
    );
} else {
    // Listar todos con paginación
    $serviciosData = $pagination->getPaginatedData(
        'servicios', 
        '*', 
        '', 
        '', 
        'id_servicio DESC'
    );
}

$servicios = $serviciosData['data'];
$paginationInfo = $serviciosData['pagination'];
?>

<div class="container-fluid px-4 servicios">
    <!-- Encabezado con estadísticas -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-concierge-bell me-2"></i>Gestión de Servicios</h2>
    </div>

    <!-- Tarjeta de Búsqueda -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-search me-2"></i>Buscar Servicios</h5>
            </div>
        </div>
        <div class="card-body p-3">
            <form method="GET" id="formBuscarServicio" class="row g-2 align-items-center">
                <div class="col-md-8">
                    <div class="input-group">
                        <input type="text" class="form-control" id="buscar_servicio" name="buscar_servicio" 
                               placeholder="Buscar por nombre..." 
                               value="<?php echo isset($_GET['buscar_servicio']) ? htmlspecialchars($_GET['buscar_servicio']) : ''; ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Buscar
                        </button>
                        <?php if (isset($_GET['buscar_servicio'])): ?>
                            <button type="button" class="btn btn-outline-secondary" onclick="limpiarBusqueda()">
                                <i class="fas fa-times me-1"></i> Limpiar
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-grid">
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#nuevoServicioModal">
                            <i class="fas fa-plus-circle me-1"></i> Nuevo Servicio
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Servicios con Paginación -->
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>
                <?php if (!empty($buscar)): ?>
                    Resultados para: "<?php echo htmlspecialchars($buscar); ?>"
                <?php else: ?>
                    Todos los Servicios
                <?php endif; ?>
            </h5>
            <div class="d-flex align-items-center">
                <?php if (!empty($buscar)): ?>
                    <span class="badge bg-primary me-2"><?php echo $paginationInfo['total_records']; ?> encontrados</span>
                <?php endif; ?>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="descargarExcel('servicios')">
                        <i class="fas fa-file-excel me-1"></i> Excel
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($servicios) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="50%" class="text-center">Servicio</th>
                                <th width="25%" class="text-center">Precio</th>
                                <th width="25%" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($servicios as $row): ?>
                            <tr>
                                <td class="text-center">
                                    <div class="d-flex align-items-center justify-content-center">
                                        <div class="flex-shrink-0 me-3">
                                            <div class="bg-light rounded-circle p-2 text-center" style="width: 40px; height: 40px;">
                                                <i class="fas fa-concierge-bell text-primary"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($row['nombre']); ?></h6>
                                        </div>
                                    </div>
                                </td>
                                <td class="fw-bold text-center">S/. <?php echo number_format($row['precio'], 2); ?></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button class="btn btn-outline-primary" onclick="editarServicio(<?php echo $row['id_servicio']; ?>)" 
                                                data-bs-toggle="tooltip" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="eliminarServicio(<?php echo $row['id_servicio']; ?>, '<?php echo htmlspecialchars($row['nombre']); ?>')" 
                                                data-bs-toggle="tooltip" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginación -->
                <?php echo $pagination->generatePaginationHTML($paginationInfo, '#/servicios'); ?>
                
            <?php else: ?>
                <div class="text-center py-5">
                    <?php if (!empty($buscar)): ?>
                        <i class="fas fa-search fa-4x text-muted mb-3"></i>
                        <h4>No se encontraron servicios</h4>
                        <p class="text-muted">No hay servicios que coincidan con tu búsqueda</p>
                    <?php else: ?>
                        <i class="fas fa-concierge-bell fa-4x text-muted mb-3"></i>
                        <h4>No hay servicios registrados</h4>
                        <p class="text-muted">Comienza agregando nuevos servicios haciendo clic en el botón "Nuevo Servicio"</p>
                        <button type="button" class="btn btn-success mt-3" data-bs-toggle="modal" data-bs-target="#nuevoServicioModal">
                            <i class="fas fa-plus-circle me-1"></i> Agregar Servicio
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal para Crear Nuevo Servicio -->
<div class="modal fade" id="nuevoServicioModal" tabindex="-1" aria-labelledby="nuevoServicioModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="nuevoServicioModalLabel">
                    <i class="fas fa-plus-circle me-2"></i> Nuevo Servicio
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="modules/servicios.php" method="POST" id="formNuevoServicio">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="nombre_servicio" class="form-label">Nombre del servicio <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-concierge-bell"></i></span>
                                    <input type="text" class="form-control" id="nombre_servicio" name="nombre" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="precio_servicio" class="form-label">Precio <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">S/.</span>
                                    <input type="number" class="form-control" id="precio_servicio" name="precio" step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="tipo_servicio" class="form-label">Tipo <span class="text-danger">*</span></label>
                                <select class="form-select" id="tipo_servicio" name="tipo" required>
                                    <option value="" disabled selected>Seleccione un tipo...</option>
                                    <option value="clinica">Clínica</option>
                                    <option value="farmacia">Farmacia</option>
                                    <option value="petshop">PetShop</option>
                                    <option value="spa">Spa</option>
                                </select>
                                <div class="form-text text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Seleccione a qué área de negocio pertenece este servicio
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancelar
                </button>
                <button type="submit" form="formNuevoServicio" class="btn btn-success">
                    <i class="fas fa-save me-1"></i> Guardar Servicio
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function limpiarBusqueda() {
    window.location.href = 'index.php#/servicios';
}

function descargarExcel(tabla) {
    window.open('modules/exportar_excel.php?tabla=' + tabla, '_blank');
}

function editarServicio(id) {
    console.log('Editar servicio:', id);
    // Implementar lógica de edición
}

function eliminarServicio(id, nombre) {
    if (confirm('¿Estás seguro de que deseas eliminar el servicio: ' + nombre + '?')) {
        // Implementar lógica de eliminación
        console.log('Eliminar servicio:', id);
    }
}

$(document).ready(function() {
    // Inicializar tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
});
</script>