<?php
include('../includes/config.php');

// Obtener todas las mascotas con información del cliente
$sql = "SELECT m.*, c.nombre as nombre_cliente, c.apellido as apellido_cliente 
        FROM mascotas m 
        INNER JOIN clientes c ON m.id_cliente = c.id_cliente 
        ORDER BY m.nombre";
$result = $conn->query($sql);

// Obtener estadísticas
$stats_sql = "SELECT 
                COUNT(*) as total_mascotas,
                SUM(CASE WHEN especie = 'Canino' THEN 1 ELSE 0 END) as caninos,
                SUM(CASE WHEN especie = 'Felino' THEN 1 ELSE 0 END) as felinos,
                SUM(CASE WHEN esterilizado = 'Si' THEN 1 ELSE 0 END) as esterilizados
              FROM mascotas";
$stats = $conn->query($stats_sql)->fetch_assoc();
?>

<div class="container-fluid px-4 mascotas">
    <!-- Encabezado con estadísticas -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-paw me-2"></i>Gestión de Mascotas</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevaMascotaModal">
            <i class="fas fa-plus-circle me-1"></i> Nueva Mascota
        </button>
    </div>

    <!-- Tarjetas de Resumen -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Total Mascotas</h6>
                            <h4 class="mb-0"><?php echo $stats['total_mascotas']; ?></h4>
                        </div>
                        <i class="fas fa-paw fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Caninos</h6>
                            <h4 class="mb-0"><?php echo $stats['caninos']; ?></h4>
                        </div>
                        <i class="fas fa-dog fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-info text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Felinos</h6>
                            <h4 class="mb-0"><?php echo $stats['felinos']; ?></h4>
                        </div>
                        <i class="fas fa-cat fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Esterilizados</h6>
                            <h4 class="mb-0"><?php echo $stats['esterilizados']; ?></h4>
                        </div>
                        <i class="fas fa-clinic-medical fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjeta de Búsqueda -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-search me-2"></i>Buscar Mascotas</h5>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownFiltros" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-filter me-1"></i> Filtros
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="dropdownFiltros">
                        <li><a class="dropdown-item" href="#" onclick="filtrarMascotas('todos')">Todas las mascotas</a></li>
                        <li><a class="dropdown-item" href="#" onclick="filtrarMascotas('caninos')">Solo caninos</a></li>
                        <li><a class="dropdown-item" href="#" onclick="filtrarMascotas('felinos')">Solo felinos</a></li>
                        <li><a class="dropdown-item" href="#" onclick="filtrarMascotas('esterilizados')">Esterilizados</a></li>
                        <li><a class="dropdown-item" href="#" onclick="filtrarMascotas('no_esterilizados')">No esterilizados</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" id="formBuscarMascota" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label for="buscar_mascota" class="form-label">Buscar por nombre o propietario</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="buscar_mascota" name="buscar_mascota" 
                               placeholder="Nombre de mascota o propietario..." 
                               value="<?php echo isset($_GET['buscar_mascota']) ? htmlspecialchars($_GET['buscar_mascota']) : ''; ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Buscar
                        </button>
                        <?php if (isset($_GET['buscar_mascota'])): ?>
                            <button type="button" class="btn btn-outline-secondary" onclick="limpiarBusquedaMascota()">
                                <i class="fas fa-times me-1"></i> Limpiar
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-grid">
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#nuevaMascotaModal">
                            <i class="fas fa-plus-circle me-1"></i> Registrar Mascota
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Resultados de Búsqueda -->
    <?php if (isset($_GET['buscar_mascota']) && !empty(trim($_GET['buscar_mascota']))): ?>
        <?php
        $buscar_mascota = trim($_GET['buscar_mascota']);
        
        $search_sql = "SELECT m.*, c.nombre as nombre_cliente, c.apellido as apellido_cliente 
                      FROM mascotas m 
                      INNER JOIN clientes c ON m.id_cliente = c.id_cliente 
                      WHERE m.nombre LIKE ? 
                         OR c.nombre LIKE ? 
                         OR c.apellido LIKE ?
                         OR CONCAT(c.nombre, ' ', c.apellido) LIKE ?
                      ORDER BY m.nombre";
        
        $stmt = $conn->prepare($search_sql);
        $searchTerm = "%$buscar_mascota%";
        $stmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
        $stmt->execute();
        $search_result = $stmt->get_result();
        ?>
        
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-search-result me-2"></i>
                    Resultados para: "<?php echo htmlspecialchars($buscar_mascota); ?>"
                </h5>
                <span class="badge bg-primary"><?php echo $search_result->num_rows; ?> encontrados</span>
            </div>
            <div class="card-body">
                <?php if ($search_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th width="100">HC</th>
                                    <th>Mascota</th>
                                    <th>Especie/Raza</th>
                                    <th>Propietario</th>
                                    <th>Edad</th>
                                    <th>Estado</th>
                                    <th width="120" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $search_result->fetch_assoc()): ?>
                                <tr>
                                    <td>HC-<?php echo str_pad($row['id_mascota'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="bg-light rounded-circle p-2 text-center" style="width: 40px; height: 40px;">
                                                    <i class="fas <?php echo $row['especie'] == 'Felino' ? 'fa-cat' : 'fa-dog'; ?> text-primary"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($row['nombre']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($row['genero']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($row['especie']); ?></span>
                                        <div class="small text-muted"><?php echo htmlspecialchars($row['raza']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['nombre_cliente'] . ' ' . $row['apellido_cliente']); ?></td>
                                    <td>
                                        <?php 
                                            $fechaNac = new DateTime($row['fecha_nacimiento']);
                                            $hoy = new DateTime();
                                            $edad = $hoy->diff($fechaNac);
                                            echo $edad->y . ' años, ' . $edad->m . ' meses';
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $row['estado'] == 'Activo' ? 'success' : 'secondary'; ?>">
                                            <?php echo htmlspecialchars($row['estado']); ?>
                                        </span>
                                        <?php if ($row['esterilizado'] == 'Si'): ?>
                                            <div class="small text-muted"><i class="fas fa-check-circle text-success"></i> Esterilizado</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-outline-primary" onclick="editarMascota(<?php echo $row['id_mascota']; ?>)" 
                                                    data-bs-toggle="tooltip" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-info" onclick="verHistoria(<?php echo $row['id_mascota']; ?>)" 
                                                    data-bs-toggle="tooltip" title="Historia Clínica">
                                                <i class="fas fa-file-medical"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary" onclick="verDetalles(<?php echo $row['id_mascota']; ?>)" 
                                                    data-bs-toggle="tooltip" title="Ver detalles">
                                                <i class="fas fa-eye"></i>
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
                        <i class="fas fa-search fa-4x text-muted mb-3"></i>
                        <h4>No se encontraron resultados</h4>
                        <p class="text-muted">No hay mascotas que coincidan con tu búsqueda</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php $stmt->close(); ?>
    <?php endif; ?>

    <!-- Lista Completa de Mascotas (cuando no hay búsqueda) -->
    <?php if (!isset($_GET['buscar_mascota'])): ?>
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Todas las Mascotas</h5>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-success" onclick="descargarExcel()">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="descargarPDF()">
                    <i class="fas fa-file-pdf me-1"></i> PDF
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if ($result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="100">HC</th>
                                <th>Mascota</th>
                                <th>Especie/Raza</th>
                                <th>Propietario</th>
                                <th>Edad</th>
                                <th>Estado</th>
                                <th width="120" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>HC-<?php echo str_pad($row['id_mascota'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0 me-3">
                                            <div class="bg-light rounded-circle p-2 text-center" style="width: 40px; height: 40px;">
                                                <i class="fas <?php echo $row['especie'] == 'Felino' ? 'fa-cat' : 'fa-dog'; ?> text-primary"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($row['nombre']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($row['genero']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($row['especie']); ?></span>
                                    <div class="small text-muted"><?php echo htmlspecialchars($row['raza']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($row['nombre_cliente'] . ' ' . $row['apellido_cliente']); ?></td>
                                <td>
                                    <?php 
                                        $fechaNac = new DateTime($row['fecha_nacimiento']);
                                        $hoy = new DateTime();
                                        $edad = $hoy->diff($fechaNac);
                                        echo $edad->y . ' años, ' . $edad->m . ' meses';
                                    ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $row['estado'] == 'Activo' ? 'success' : 'secondary'; ?>">
                                        <?php echo htmlspecialchars($row['estado']); ?>
                                    </span>
                                    <?php if ($row['esterilizado'] == 'Si'): ?>
                                        <div class="small text-muted"><i class="fas fa-check-circle text-success"></i> Esterilizado</div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button class="btn btn-outline-primary" onclick="editarMascota(<?php echo $row['id_mascota']; ?>)" 
                                                data-bs-toggle="tooltip" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-info" onclick="verHistoria(<?php echo $row['id_mascota']; ?>)" 
                                                data-bs-toggle="tooltip" title="Historia Clínica">
                                            <i class="fas fa-file-medical"></i>
                                        </button>
                                        <button class="btn btn-outline-secondary" onclick="verDetalles(<?php echo $row['id_mascota']; ?>)" 
                                                data-bs-toggle="tooltip" title="Ver detalles">
                                            <i class="fas fa-eye"></i>
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
                    <i class="fas fa-paw fa-4x text-muted mb-3"></i>
                    <h4>No hay mascotas registradas</h4>
                    <p class="text-muted">Comienza registrando nuevas mascotas haciendo clic en el botón "Nueva Mascota"</p>
                    <button type="button" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#nuevaMascotaModal">
                        <i class="fas fa-plus-circle me-1"></i> Registrar Mascota
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal para Nueva Mascota -->
    <div class="modal fade" id="nuevaMascotaModal" tabindex="-1" aria-labelledby="nuevaMascotaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="nuevaMascotaModalLabel">
                        <i class="fas fa-paw me-2"></i> Registrar Nueva Mascota
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="modules/mascotas.php" method="POST" id="formNuevaMascota">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h6 class="border-bottom pb-2"><i class="fas fa-user me-2"></i>Datos del Propietario</h6>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label for="buscar_propietario" class="form-label">Buscar Propietario <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                        <input type="text" class="form-control" id="buscar_propietario" placeholder="Nombre, apellido o documento...">
                                        <input type="hidden" id="id_cliente" name="id_cliente" required>
                                    </div>
                                    <div id="resultados_propietario" class="mt-2"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h6 class="border-bottom pb-2"><i class="fas fa-paw me-2"></i>Datos de la Mascota</h6>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nombre_mascota" class="form-label">Nombre <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                        <input type="text" class="form-control" id="nombre_mascota" name="nombre" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="fecha_nacimiento" class="form-label">Fecha Nacimiento <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                        <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="especie" class="form-label">Especie <span class="text-danger">*</span></label>
                                    <select class="form-select" id="especie" name="especie" required>
                                        <option value="">Seleccionar...</option>
                                        <option value="Canino">Canino</option>
                                        <option value="Felino">Felino</option>
                                        <option value="Ave">Ave</option>
                                        <option value="Roedor">Roedor</option>
                                        <option value="Otro">Otro</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="raza" class="form-label">Raza</label>
                                    <select class="form-select" id="raza" name="raza">
                                        <option value="">Seleccionar...</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="genero" class="form-label">Género <span class="text-danger">*</span></label>
                                    <select class="form-select" id="genero" name="genero" required>
                                        <option value="">Seleccionar...</option>
                                        <option value="Macho">Macho</option>
                                        <option value="Hembra">Hembra</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="esterilizado" class="form-label">Esterilizado</label>
                                    <select class="form-select" id="esterilizado" name="esterilizado">
                                        <option value="No">No</option>
                                        <option value="Si">Sí</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="color" class="form-label">Color</label>
                                    <input type="text" class="form-control" id="color" name="color">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label for="observaciones" class="form-label">Observaciones</label>
                                    <textarea class="form-control" id="observaciones" name="observaciones" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <h6 class="border-bottom pb-2"><i class="fas fa-camera me-2"></i>Fotografía</h6>
                                <div class="text-center border rounded p-4">
                                    <i class="fas fa-image fa-3x text-muted mb-3"></i>
                                    <p class="text-muted small">Arrastra una imagen aquí o haz clic para seleccionar</p>
                                    <input type="file" class="d-none" id="fotoMascota" accept="image/*">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('fotoMascota').click()">
                                        <i class="fas fa-upload me-1"></i> Subir imagen
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancelar
                    </button>
                    <button type="submit" form="formNuevaMascota" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Guardar Mascota
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Funciones principales
function editarMascota(id) {
    $.ajax({
        url: 'modules/editar_mascota.php?id=' + id,
        method: 'GET',
        success: function(response) {
            $('#contenidoModalEditar').html(response);
            $('#editarMascotaModal').modal('show');
        },
        error: function() {
            Swal.fire('Error', 'No se pudo cargar la información de la mascota', 'error');
        }
    });
}

function verHistoria(id) {
    window.location.href = 'index.php#/historia-clinica?id=' + id;
}

function verDetalles(id) {
    $.ajax({
        url: 'modules/detalles_mascota.php?id=' + id,
        method: 'GET',
        success: function(response) {
            $('#contenidoModalDetalles').html(response);
            $('#detallesMascotaModal').modal('show');
        }
    });
}

function filtrarMascotas(tipo) {
    let url = 'modules/mascotas.php?filtro=' + tipo;
    
    if (tipo === 'todos') {
        url = 'modules/mascotas.php';
    }
    
    $.ajax({
        url: url,
        method: 'GET',
        success: function(response) {
            $('#contenido').html(response);
        },
        error: function() {
            console.error('Error al filtrar mascotas');
        }
    });
}

function realizarBusquedaMascota() {
    const formData = new FormData(document.getElementById('formBuscarMascota'));
    const searchParams = new URLSearchParams(formData);
    
    $.ajax({
        url: 'modules/mascotas.php?' + searchParams.toString(),
        method: 'GET',
        success: function(response) {
            $('#contenido').html(response);
        },
        error: function() {
            console.error('Error al realizar la búsqueda');
        }
    });
}

function limpiarBusquedaMascota() {
    $.ajax({
        url: 'modules/mascotas.php',
        method: 'GET',
        success: function(response) {
            $('#contenido').html(response);
        },
        error: function() {
            console.error('Error al limpiar la búsqueda');
        }
    });
}

function descargarExcel() {
    const tipoAuditoria = document.getElementById('auditoria_select').value;
    if (!tipoAuditoria) {
        Swal.fire('Advertencia', 'Por favor selecciona un tipo de auditoría', 'warning');
        return;
    }
    window.open('exportar_excel.php?tipo=' + tipoAuditoria, '_blank');
}

function descargarPDF() {
    const tipoAuditoria = document.getElementById('auditoria_select').value;
    if (!tipoAuditoria) {
        Swal.fire('Advertencia', 'Por favor selecciona un tipo de auditoría', 'warning');
        return;
    }
    window.open('exportar_pdf.php?tipo=' + tipoAuditoria, '_blank');
}

// Eventos al cargar la página
$(document).ready(function() {
    // Inicializar tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Evento para el formulario de búsqueda
    $('#formBuscarMascota').on('submit', function(e) {
        e.preventDefault();
        realizarBusquedaMascota();
    });
    
    // Buscar propietario en tiempo real
    $('#buscar_propietario').on('input', function() {
        const busqueda = $(this).val();
        if (busqueda.length >= 2) {
            $.ajax({
                url: 'modules/buscar_propietario.php',
                method: 'GET',
                data: { q: busqueda },
                success: function(response) {
                    $('#resultados_propietario').html(response);
                }
            });
        } else {
            $('#resultados_propietario').html('');
        }
    });
    
    // Cargar razas según especie
    $('#especie').on('change', function() {
        const especie = $(this).val();
        cargarRazas(especie);
    });
    
    // Validación del formulario
    $('#formNuevaMascota').validate({
        rules: {
            id_cliente: {
                required: true
            },
            nombre: {
                required: true,
                minlength: 2
            },
            fecha_nacimiento: {
                required: true,
                date: true
            },
            especie: {
                required: true
            },
            genero: {
                required: true
            }
        },
        messages: {
            id_cliente: {
                required: "Debe seleccionar un propietario"
            },
            nombre: {
                required: "El nombre de la mascota es obligatorio",
                minlength: "El nombre debe tener al menos 2 caracteres"
            },
            fecha_nacimiento: {
                required: "La fecha de nacimiento es obligatoria",
                date: "Ingrese una fecha válida"
            },
            especie: {
                required: "Debe seleccionar la especie"
            },
            genero: {
                required: "Debe seleccionar el género"
            }
        },
        errorElement: 'span',
        errorPlacement: function (error, element) {
            error.addClass('invalid-feedback');
            element.closest('.form-group').append(error);
        },
        highlight: function (element, errorClass, validClass) {
            $(element).addClass('is-invalid');
        },
        unhighlight: function (element, errorClass, validClass) {
            $(element).removeClass('is-invalid');
        }
    });
});

// Funciones auxiliares
function seleccionarPropietario(id, nombre) {
    $('#id_cliente').val(id);
    $('#buscar_propietario').val(nombre);
    $('#resultados_propietario').html('<div class="alert alert-success py-2">Propietario seleccionado: <strong>' + nombre + '</strong></div>');
}

function cargarRazas(especie) {
    const razas = {
        'Canino': ['Labrador', 'Golden Retriever', 'Pastor Alemán', 'Bulldog', 'Beagle', 'Poodle', 'Rottweiler', 'Yorkshire', 'Chihuahua', 'Mestizo'],
        'Felino': ['Persa', 'Siamés', 'Maine Coon', 'Británico', 'Ragdoll', 'Bengalí', 'Abisinio', 'Mestizo'],
        'Ave': ['Canario', 'Periquito', 'Loro', 'Cacatúa', 'Cotorra'],
        'Roedor': ['Hamster', 'Conejo', 'Cobayo', 'Chinchilla']
    };
    
    const select = $('#raza');
    select.html('<option value="">Seleccionar...</option>');
    
    if (razas[especie]) {
        razas[especie].forEach(function(raza) {
            select.append('<option value="' + raza + '">' + raza + '</option>');
        });
    }
}
</script>