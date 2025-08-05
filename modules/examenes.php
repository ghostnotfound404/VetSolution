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
                                <th>Mascota / Propietario</th>
                                <th>Laboratorio</th>
                                <th>Tipo de Análisis</th>
                                <th>Costo</th>
                                <th>Estado Pago</th>
                                <th>Fecha Envío</th>
                                <th>Lectura</th>
                                <th width="120" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($examen = $examenes->fetch_assoc()): ?>
                                <tr>
                                    <td>
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
                                    <td><?php echo htmlspecialchars($examen['laboratorio']); ?></td>
                                    <td><?php echo htmlspecialchars($examen['tipo_analisis']); ?></td>
                                    <td>S/ <?php echo number_format($examen['costo'], 2); ?></td>
                                    <td>
                                        <?php if ($examen['pagado']): ?>
                                            <span class="badge bg-success">Pagado</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pendiente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($examen['fecha_envio'])); ?></td>
                                    <td>
                                        <?php if ($examen['lectura_realizada']): ?>
                                            <span class="badge bg-info">Realizada</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pendiente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-outline-primary" onclick="editarExamen(<?php echo $examen['id_examen_lab']; ?>)" 
                                                    data-bs-toggle="tooltip" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="eliminarExamen(<?php echo $examen['id_examen_lab']; ?>)" 
                                                    data-bs-toggle="tooltip" title="Eliminar">
                                                <i class="fas fa-trash"></i>
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Examen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contenidoModalEditar">
                <!-- El contenido se carga dinámicamente -->
            </div>
        </div>
    </div>
</div>

<!-- Modal para Nuevo Examen -->
<div class="modal fade" id="nuevoExamenModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-vial me-2"></i>Registrar Nuevo Examen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formNuevoExamen">
                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="mascota_search" class="form-label">Buscar y Seleccionar Mascota <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <input type="text" class="form-control" id="mascota_search" 
                                       placeholder="Escriba el nombre de la mascota o del propietario..." autocomplete="off">
                                <input type="hidden" id="mascota_select" name="id_mascota" required>
                                <div id="mascota_results" class="dropdown-menu w-100" style="max-height: 200px; overflow-y: auto;"></div>
                            </div>
                            <small class="text-muted">Escriba al menos 2 caracteres para buscar</small>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="laboratorio" class="form-label">Laboratorio <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="laboratorio" name="laboratorio" required 
                                   placeholder="Nombre del laboratorio" style="text-transform: uppercase;">
                        </div>
                        <div class="col-md-6">
                            <label for="tipo_analisis" class="form-label">Tipo de Análisis <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="tipo_analisis" name="tipo_analisis" required 
                                   placeholder="Ej: Hemograma completo, Perfil bioquímico...">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="costo" class="form-label">Costo (S/) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="costo" name="costo" required min="0" placeholder="0.00">
                        </div>
                        <div class="col-md-6">
                            <label for="fecha_envio" class="form-label">Fecha de Envío <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="fecha_envio" name="fecha_envio" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="pagado" name="pagado">
                                <label class="form-check-label" for="pagado">
                                    <i class="fas fa-money-bill-wave me-1"></i> ¿Está pagado?
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="lectura_realizada" name="lectura_realizada">
                                <label class="form-check-label" for="lectura_realizada">
                                    <i class="fas fa-eye me-1"></i> ¿Se realizó la lectura?
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancelar
                </button>
                <button type="submit" form="formNuevoExamen" class="btn btn-success">
                    <i class="fas fa-save me-1"></i> Guardar Examen
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
                                <div class="dropdown-item cursor-pointer" data-id="${mascota.id_mascota}">
                                    <strong>${mascota.nombre}</strong><br>
                                    <small class="text-muted">${mascota.nombre_cliente} ${mascota.apellido_cliente}</small>
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
                        resultsDiv.html('<div class="dropdown-item text-muted">No se encontraron mascotas</div>');
                    }
                },
                error: function() {
                    resultsDiv.html('<div class="dropdown-item text-danger">Error al buscar mascotas</div>');
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
                        confirmButtonColor: '#198754'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message,
                        confirmButtonColor: '#dc3545'
                    });
                }
            },
            error: function() {
                $('#formNuevoExamen').data('submitting', false);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al guardar el examen',
                    confirmButtonColor: '#dc3545'
                });
            }
        });
    });
});

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
    $.ajax({
        url: 'modules/editar_examen.php?id=' + id,
        method: 'GET',
        success: function(response) {
            $('#contenidoModalEditar').html(response);
            $('#editarExamenModal').modal('show');
        },
        error: function() {
            Swal.fire('Error', 'No se pudo cargar la información del examen', 'error');
        }
    });
}

function eliminarExamen(id) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "Esta acción no se puede deshacer",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'modules/eliminar_examen.php',
                method: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Eliminado',
                            text: response.message,
                            confirmButtonColor: '#198754'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message,
                            confirmButtonColor: '#dc3545'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se pudo completar la solicitud',
                        confirmButtonColor: '#dc3545'
                    });
                }
            });
        }
    });
}
</script>
