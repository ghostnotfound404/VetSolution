<?php
include('../includes/config.php');

// Obtener todos los clientes
$sql = "SELECT * FROM clientes";
$result = $conn->query($sql);

// Obtener estadísticas
$stats_sql = "SELECT COUNT(*) as total_clientes FROM clientes";
$stats = $conn->query($stats_sql)->fetch_assoc();
?>

<div class="container-fluid px-4 clientes">
    <!-- Encabezado con estadísticas -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-users me-2"></i>Gestión de Clientes</h2>
    </div>

    <!-- Tarjeta de Resumen -->
    <div class="row mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Total Clientes</h6>
                            <h4 class="mb-0"><?php echo $stats['total_clientes']; ?></h4>
                        </div>
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjeta de Búsqueda -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-search me-2"></i>Buscar Clientes</h5>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" id="formBuscarCliente" class="row g-3 align-items-center">
                <div class="col-md-8">
                    <div class="input-group">
                        <input type="text" class="form-control" id="buscar_nombre" name="buscar_nombre" 
                               placeholder="Buscar por nombre, apellido o DNI..." 
                               value="<?php echo isset($_GET['buscar_nombre']) ? htmlspecialchars($_GET['buscar_nombre']) : ''; ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Buscar
                        </button>
                        <?php if (isset($_GET['buscar_nombre'])): ?>
                            <button type="button" class="btn btn-outline-secondary" onclick="limpiarBusqueda()">
                                <i class="fas fa-times me-1"></i> Limpiar
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-grid">
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#nuevoClienteModal">
                            <i class="fas fa-plus-circle me-1"></i> Nuevo Cliente
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Resultados de Búsqueda -->
    <?php if (isset($_GET['buscar_nombre']) && !empty(trim($_GET['buscar_nombre']))): ?>
        <?php
        $buscar_nombre = trim($_GET['buscar_nombre']);
        
        $search_sql = "SELECT * FROM clientes 
                      WHERE nombre LIKE ? 
                         OR apellido LIKE ? 
                         OR dni LIKE ?
                         OR CONCAT(nombre, ' ', apellido) LIKE ?
                      ORDER BY nombre, apellido";
        
        $stmt = $conn->prepare($search_sql);
        $searchTerm = "%$buscar_nombre%";
        $stmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
        $stmt->execute();
        $search_result = $stmt->get_result();
        ?>
        
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-search-result me-2"></i>
                    Resultados para: "<?php echo htmlspecialchars($buscar_nombre); ?>"
                </h5>
                <span class="badge bg-primary"><?php echo $search_result->num_rows; ?> encontrados</span>
            </div>
            <div class="card-body">
                <?php if ($search_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Cliente</th>
                                    <th>Contacto</th>
                                    <th>DNI</th>
                                    <th>Dirección</th>
                                    <th width="150" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $search_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="bg-light rounded-circle p-2 text-center" style="width: 40px; height: 40px;">
                                                    <i class="fas fa-user text-primary"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($row['nombre'] . ' ' . $row['apellido']); ?></h6>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <i class="fas fa-phone text-muted me-2"></i>
                                        <?php echo htmlspecialchars($row['celular']); ?>
                                    </td>
                                    <td>
                                        <?php echo !empty($row['dni']) ? htmlspecialchars($row['dni']) : '<span class="text-muted">No registrado</span>'; ?>
                                    </td>
                                    <td>
                                        <?php echo !empty($row['direccion']) ? htmlspecialchars($row['direccion']) : '<span class="text-muted">No registrada</span>'; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-outline-primary" onclick="editarCliente(<?php echo $row['id_cliente']; ?>)" 
                                                    data-bs-toggle="tooltip" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-info" onclick="verMascotas(<?php echo $row['id_cliente']; ?>)" 
                                                    data-bs-toggle="tooltip" title="Ver Mascotas">
                                                <i class="fas fa-paw"></i>
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
                        <p class="text-muted">No hay clientes que coincidan con tu búsqueda</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php $stmt->close(); ?>
    <?php endif; ?>

    <!-- Lista Completa de Clientes (cuando no hay búsqueda) -->
    <?php if (!isset($_GET['buscar_nombre'])): ?>
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Todos los Clientes</h5>
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
                                <th>Cliente</th>
                                <th>Contacto</th>
                                <th>DNI</th>
                                <th>Dirección</th>
                                <th width="150" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0 me-3">
                                            <div class="bg-light rounded-circle p-2 text-center" style="width: 40px; height: 40px;">
                                                <i class="fas fa-user text-primary"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($row['nombre'] . ' ' . $row['apellido']); ?></h6>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <i class="fas fa-phone text-muted me-2"></i>
                                    <?php echo htmlspecialchars($row['celular']); ?>
                                </td>
                                <td>
                                    <?php echo !empty($row['dni']) ? htmlspecialchars($row['dni']) : '<span class="text-muted">No registrado</span>'; ?>
                                </td>
                                <td>
                                    <?php echo !empty($row['direccion']) ? htmlspecialchars($row['direccion']) : '<span class="text-muted">No registrada</span>'; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button class="btn btn-outline-primary" onclick="editarCliente(<?php echo $row['id_cliente']; ?>)" 
                                                data-bs-toggle="tooltip" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-info" onclick="verMascotas(<?php echo $row['id_cliente']; ?>)" 
                                                data-bs-toggle="tooltip" title="Ver Mascotas">
                                            <i class="fas fa-paw"></i>
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
                    <i class="fas fa-users fa-4x text-muted mb-3"></i>
                    <h4>No hay clientes registrados</h4>
                    <p class="text-muted">Comienza registrando nuevos clientes haciendo clic en el botón "Nuevo Cliente"</p>
                    <button type="button" class="btn btn-success mt-3" data-bs-toggle="modal" data-bs-target="#nuevoClienteModal">
                        <i class="fas fa-plus-circle me-1"></i> Registrar Cliente
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal para Nuevo Cliente -->
    <div class="modal fade" id="nuevoClienteModal" tabindex="-1" aria-labelledby="nuevoClienteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="nuevoClienteModalLabel">
                        <i class="fas fa-user-plus me-2"></i> Registrar Nuevo Cliente
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="modules/clientes.php" method="POST" id="formNuevoCliente">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="nombre" name="nombre" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="apellido" class="form-label">Apellido <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="apellido" name="apellido" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="celular" class="form-label">Celular <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="tel" class="form-control" id="celular" name="celular" 
                                           pattern="[0-9]{9}" maxlength="9" 
                                           placeholder="987654321" required>
                                </div>
                                <small class="text-muted">9 dígitos (sin espacios ni guiones)</small>
                            </div>
                            <div class="col-md-6">
                                <label for="dni" class="form-label">DNI</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                    <input type="text" class="form-control" id="dni" name="dni" 
                                           pattern="[0-9]{8}" maxlength="8" 
                                           placeholder="12345678">
                                </div>
                                <small class="text-muted">8 dígitos (opcional)</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="direccion" class="form-label">Dirección</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                <textarea class="form-control" id="direccion" name="direccion" rows="2" placeholder="Opcional"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancelar
                    </button>
                    <button type="submit" form="formNuevoCliente" class="btn btn-success">
                        <i class="fas fa-save me-1"></i> Guardar Cliente
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Funciones para los botones de acción
function editarCliente(id) {
    $.ajax({
        url: 'modules/editar_cliente.php?id=' + id,
        method: 'GET',
        success: function(response) {
            $('#contenidoModalEditar').html(response);
            $('#editarClienteModal').modal('show');
        },
        error: function() {
            alert('Error al cargar los datos del cliente');
        }
    });
}

function verMascotas(id) {
    window.location.href = 'index.php#/mascotas?cliente=' + id;
}

function realizarBusqueda() {
    const formData = new FormData(document.getElementById('formBuscarCliente'));
    const searchParams = new URLSearchParams(formData);
    
    $.ajax({
        url: 'modules/clientes.php?' + searchParams.toString(),
        method: 'GET',
        success: function(response) {
            $('#contenido').html(response);
        },
        error: function() {
            console.error('Error al realizar la búsqueda');
        }
    });
}

function limpiarBusqueda() {
    $.ajax({
        url: 'modules/clientes.php',
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
    $('#formBuscarCliente').on('submit', function(e) {
        e.preventDefault();
        realizarBusqueda();
    });
    
    // Validación del formulario de nuevo cliente
    $('#formNuevoCliente').validate({
        rules: {
            nombre: {
                required: true,
                minlength: 2
            },
            apellido: {
                required: true,
                minlength: 2
            },
            celular: {
                required: true,
                digits: true,
                minlength: 9,
                maxlength: 9
            },
            dni: {
                digits: true,
                minlength: 8,
                maxlength: 8
            }
        },
        messages: {
            nombre: {
                required: "Por favor ingresa el nombre del cliente",
                minlength: "El nombre debe tener al menos 2 caracteres"
            },
            apellido: {
                required: "Por favor ingresa el apellido del cliente",
                minlength: "El apellido debe tener al menos 2 caracteres"
            },
            celular: {
                required: "Por favor ingresa el número de celular",
                digits: "El celular debe contener solo números",
                minlength: "El celular debe tener exactamente 9 dígitos",
                maxlength: "El celular debe tener exactamente 9 dígitos"
            },
            dni: {
                digits: "El DNI debe contener solo números",
                minlength: "El DNI debe tener exactamente 8 dígitos",
                maxlength: "El DNI debe tener exactamente 8 dígitos"
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
</script>