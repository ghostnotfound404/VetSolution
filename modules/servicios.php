<?php
include('../includes/config.php');

// Procesar formulario de nuevo servicio
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $precio = trim($_POST['precio']);
    $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';

    // Validaciones
    $errores = [];
    
    if (empty($nombre)) {
        $errores[] = "El nombre del servicio es obligatorio";
    }
    
    if (empty($precio) || $precio <= 0) {
        $errores[] = "El precio debe ser mayor a 0";
    }

    if (empty($errores)) {
        $insert_sql = "INSERT INTO servicios (nombre, precio, descripcion) 
                       VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("sds", $nombre, $precio, $descripcion);
        
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

// Obtener servicios (con búsqueda si se especifica)
if (isset($_GET['buscar_servicio']) && !empty(trim($_GET['buscar_servicio']))) {
    $buscar_servicio = trim($_GET['buscar_servicio']);
    
    // Usar prepared statement para seguridad
    $search_sql = "SELECT * FROM servicios 
                  WHERE nombre LIKE ? 
                     OR descripcion LIKE ?
                  ORDER BY nombre";
    
    $stmt = $conn->prepare($search_sql);
    $searchTerm = "%$buscar_servicio%";
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    // Obtener todos los servicios
    $sql = "SELECT * FROM servicios ORDER BY nombre";
    $result = $conn->query($sql);
}
?>

<div class="container servicios">
    <h2>Gestionar Servicios</h2>

    <!-- Sección para Buscar Servicio y Crear Nuevo -->
    <div class="mb-4 search-section">
        <div class="row align-items-end">
            <div class="col-md-8">
                <h4>Buscar Servicio</h4>
                <form method="GET" class="d-flex gap-2" id="formBuscarServicio">
                    <div class="flex-grow-1">
                        <input type="text" class="form-control" id="buscar_servicio" name="buscar_servicio" 
                               placeholder="Buscar por nombre o descripción..." 
                               value="<?php echo isset($_GET['buscar_servicio']) ? htmlspecialchars($_GET['buscar_servicio']) : ''; ?>">
                    </div>
                    <button type="submit" class="btn btn-secondary">Buscar</button>
                    <?php if (isset($_GET['buscar_servicio'])): ?>
                        <button type="button" class="btn btn-outline-secondary" onclick="limpiarBusquedaServicio()">Limpiar</button>
                    <?php endif; ?>
                </form>
            </div>
            <div class="col-md-4 text-end">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#nuevoServicioModal">
                    <i class="fas fa-plus"></i> Crear Nuevo Servicio
                </button>
            </div>
        </div>
    </div>

    <!-- Sección de Auditoría -->
    <div class="mb-4">
        <h4>Auditoría</h4>
        <div class="row">
            <div class="col-md-6">
                <select class="form-select" id="auditoria_select">
                    <option value="">Seleccionar tipo de auditoría...</option>
                    <option value="servicios_general">Servicios - Reporte General</option>
                    <option value="servicios_mas_solicitados">Servicios Más Solicitados</option>
                    <option value="servicios_ingresos">Ingresos por Servicios</option>
                    <option value="servicios_precios">Lista de Precios</option>
                </select>
            </div>
            <div class="col-md-6">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-success" onclick="descargarExcel()">
                        <i class="fas fa-file-excel"></i> Descargar Excel
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="descargarPDF()">
                        <i class="fas fa-file-pdf"></i> Descargar PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de servicios -->
    <div class="mb-4">
        <h4>Todos los Servicios</h4>
        <?php if ($result->num_rows > 0): ?>
            <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>Nombre del Servicio</th>
                            <th>Precio</th>
                            <th>Descripción</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>SRV-<?php echo $row['id_servicio']; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['nombre']); ?></strong></td>
                            <td>S/. <?php echo number_format($row['precio'], 2); ?></td>
                            <td><?php echo htmlspecialchars($row['descripcion'] ?? 'Sin descripción'); ?></td>
                            <td>
                                <button class="btn btn-warning btn-sm" onclick="editarServicio(<?php echo $row['id_servicio']; ?>)">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="eliminarServicio(<?php echo $row['id_servicio']; ?>, '<?php echo htmlspecialchars($row['nombre']); ?>')">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
        <?php else: ?>
            <div class="alert alert-info">No hay servicios registrados.</div>
        <?php endif; ?>
    </div>

    <!-- Modal para Crear Nuevo Servicio -->
    <div class="modal fade" id="nuevoServicioModal" tabindex="-1" aria-labelledby="nuevoServicioModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="nuevoServicioModalLabel">Crear Nuevo Servicio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="modules/servicios.php" method="POST" id="formNuevoServicio">
                        <div class="mb-3">
                            <label for="nombre_servicio" class="form-label">Nombre del servicio <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-stethoscope"></i></span>
                                <input type="text" class="form-control" id="nombre_servicio" name="nombre" required placeholder="Ej: Consulta general, Vacunación, Cirugía">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="precio_servicio" class="form-label">Precio (S/.) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                                <input type="number" class="form-control" id="precio_servicio" name="precio" step="0.01" min="0" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descripcion_servicio" class="form-label">Descripción del servicio</label>
                            <textarea class="form-control" id="descripcion_servicio" name="descripcion" rows="3" placeholder="Descripción detallada del servicio"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="formNuevoServicio" class="btn btn-success">Guardar Servicio</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function editarServicio(id) {
    alert('Función de editar servicio ID: ' + id + ' (por implementar)');
}

function eliminarServicio(id, nombre) {
    if (confirm('¿Está seguro de que desea eliminar el servicio "' + nombre + '"?')) {
        // TODO: Implementar eliminación
        alert('Funcionalidad de eliminación por implementar');
    }
}

// Función para realizar búsqueda de servicios
function realizarBusquedaServicio() {
    const formData = new FormData(document.getElementById('formBuscarServicio'));
    const searchParams = new URLSearchParams(formData);
    
    $.ajax({
        url: 'modules/servicios.php?' + searchParams.toString(),
        method: 'GET',
        success: function(response) {
            $('#contenido').html(response);
        },
        error: function() {
            console.error('Error al realizar la búsqueda');
        }
    });
}

// Función para limpiar búsqueda de servicios
function limpiarBusquedaServicio() {
    $.ajax({
        url: 'modules/servicios.php',
        method: 'GET',
        success: function(response) {
            $('#contenido').html(response);
        },
        error: function() {
            console.error('Error al limpiar la búsqueda');
        }
    });
}

// Event listeners para el formulario de búsqueda
$(document).ready(function() {
    $('#formBuscarServicio').on('submit', function(e) {
        e.preventDefault();
        realizarBusquedaServicio();
    });
});

// Funciones de auditoría
function descargarExcel() {
    const tipoAuditoria = document.getElementById('auditoria_select').value;
    if (!tipoAuditoria) {
        alert('Por favor selecciona un tipo de auditoría');
        return;
    }
    // Aquí implementarías la lógica para generar y descargar el Excel
    window.open('exportar_excel.php?tipo=' + tipoAuditoria, '_blank');
}

function descargarPDF() {
    const tipoAuditoria = document.getElementById('auditoria_select').value;
    if (!tipoAuditoria) {
        alert('Por favor selecciona un tipo de auditoría');
        return;
    }
    // Aquí implementarías la lógica para generar y descargar el PDF
    window.open('exportar_pdf.php?tipo=' + tipoAuditoria, '_blank');
}
</script>

<!-- JS específico para Servicios -->
<script src="assets/js/servicios.js"></script>
