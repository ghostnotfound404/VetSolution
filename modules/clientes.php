<?php
include('../includes/config.php');
include('../includes/pagination.php');

// Optimizar base de datos al cargar por primera vez
if (!isset($_SESSION['db_optimized'])) {
    optimizeDatabase($conn);
    $_SESSION['db_optimized'] = true;
}

// Inicializar paginación
$pagination = new PaginationHelper($conn, 10);

// Procesar formulario de nuevo cliente
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $celular = trim($_POST['celular']);
    $dni = isset($_POST['dni']) ? trim($_POST['dni']) : '';
    $direccion = isset($_POST['direccion']) ? trim($_POST['direccion']) : '';

    // Validaciones
    $errores = [];
    
    if (empty($nombre)) {
        $errores[] = "El nombre del cliente es obligatorio";
    }
    
    if (empty($apellido)) {
        $errores[] = "El apellido del cliente es obligatorio";
    }
    
    if (empty($celular)) {
        $errores[] = "El celular del cliente es obligatorio";
    } elseif (!preg_match('/^[0-9]{9}$/', $celular)) {
        $errores[] = "El celular debe tener exactamente 9 dígitos";
    }
    
    if (!empty($dni) && !preg_match('/^[0-9]{8}$/', $dni)) {
        $errores[] = "El DNI debe tener exactamente 8 dígitos";
    }
    
    // Verificar si el DNI ya existe (si se proporcionó)
    if (!empty($dni)) {
        $check_dni_sql = "SELECT id_cliente FROM clientes WHERE dni = ?";
        $stmt_check = $conn->prepare($check_dni_sql);
        $stmt_check->bind_param("s", $dni);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows > 0) {
            $errores[] = "Ya existe un cliente registrado con este DNI";
        }
        $stmt_check->close();
    }

    if (empty($errores)) {
        $insert_sql = "INSERT INTO clientes (nombre, apellido, celular, dni, direccion) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("sssss", $nombre, $apellido, $celular, $dni, $direccion);
        
        if ($stmt->execute()) {
            // Respuesta JSON para éxito
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Cliente registrado correctamente']);
            exit();
        } else {
            // Respuesta JSON para error
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al guardar cliente: ' . $conn->error]);
            exit();
        }
        $stmt->close();
    } else {
        // Respuesta JSON para errores de validación
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => implode('\n', $errores)]);
        exit();
    }
}

// Obtener datos paginados
$searchTerm = isset($_GET['buscar_nombre']) ? trim($_GET['buscar_nombre']) : '';

if (!empty($searchTerm)) {
    // Búsqueda con paginación
    $searchFields = ['nombre', 'apellido', 'dni', 'celular', "CONCAT(nombre, ' ', apellido)"];
    $clientesData = $pagination->searchWithPagination(
        'clientes', 
        $searchFields, 
        $searchTerm, 
        '*', 
        '', 
        'fecha_registro DESC'
    );
} else {
    // Listar todos con paginación
    $clientesData = $pagination->getPaginatedData(
        'clientes', 
        '*', 
        '', 
        '', 
        'fecha_registro DESC'
    );
}

$clientes = $clientesData['data'];
$paginationInfo = $clientesData['pagination'];

// Obtener estadísticas generales (sin paginación)
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
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-search-result me-2"></i>
                    Resultados para: "<?php echo htmlspecialchars($_GET['buscar_nombre']); ?>"
                </h5>
                <span class="badge bg-primary"><?php echo $paginationInfo['total_records']; ?> encontrados</span>
            </div>
            <div class="card-body">
                <?php if (count($clientes) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th width="25%" class="d-none d-sm-table-cell">Cliente</th>
                                    <th width="100%" class="d-table-cell d-sm-none">Información del Cliente</th>
                                    <th width="20%" class="d-none d-md-table-cell">Contacto</th>
                                    <th width="15%" class="d-none d-lg-table-cell">DNI</th>
                                    <th width="25%" class="d-none d-lg-table-cell">Dirección</th>
                                    <th width="15%" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientes as $row): ?>
                                <tr>
                                    <!-- Vista Desktop - Cliente -->
                                    <td class="d-none d-sm-table-cell">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="bg-light rounded-circle p-2 text-center" style="width: 40px; height: 40px;">
                                                    <i class="fas fa-user text-primary"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($row['nombre'] . ' ' . $row['apellido']); ?></h6>
                                                <small class="text-muted">ID: <?php echo $row['id_cliente']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Vista Mobile - Información Completa -->
                                    <td class="d-table-cell d-sm-none">
                                        <div class="card border-0 bg-light">
                                            <div class="card-body p-3">
                                                <div class="d-flex align-items-start mb-2">
                                                    <div class="flex-shrink-0 me-3">
                                                        <div class="bg-white rounded-circle p-2 text-center" style="width: 45px; height: 45px;">
                                                            <i class="fas fa-user text-primary" style="font-size: 16px;"></i>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($row['nombre'] . ' ' . $row['apellido']); ?></h6>
                                                        <div class="row g-2 text-sm">
                                                            <div class="col-6">
                                                                <span class="text-muted">Celular:</span>
                                                                <div class="fw-bold">
                                                                    <i class="fas fa-phone text-success me-1"></i>
                                                                    <?php echo htmlspecialchars($row['celular']); ?>
                                                                </div>
                                                            </div>
                                                            <div class="col-6">
                                                                <span class="text-muted">DNI:</span>
                                                                <div class="fw-bold">
                                                                    <?php echo !empty($row['dni']) ? htmlspecialchars($row['dni']) : '<span class="text-muted small">No registrado</span>'; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="mt-2">
                                                            <span class="text-muted small">Dirección:</span>
                                                            <div class="fw-bold small">
                                                                <?php echo !empty($row['direccion']) ? htmlspecialchars($row['direccion']) : '<span class="text-muted">No registrada</span>'; ?>
                                                            </div>
                                                        </div>
                                                        <div class="mt-2">
                                                            <span class="badge bg-primary rounded-pill">
                                                                <i class="fas fa-calendar me-1"></i>
                                                                Registrado: <?php echo date('d/m/Y', strtotime($row['fecha_registro'])); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        <i class="fas fa-phone text-muted me-2"></i>
                                        <?php echo htmlspecialchars($row['celular']); ?>
                                    </td>
                                    <td class="d-none d-lg-table-cell">
                                        <?php echo !empty($row['dni']) ? htmlspecialchars($row['dni']) : '<span class="text-muted">No registrado</span>'; ?>
                                    </td>
                                    <td class="d-none d-lg-table-cell">
                                        <?php echo !empty($row['direccion']) ? htmlspecialchars($row['direccion']) : '<span class="text-muted">No registrada</span>'; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group d-none d-md-flex" role="group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editarCliente(<?php echo $row['id_cliente']; ?>)" 
                                                    data-bs-toggle="tooltip" title="Editar cliente">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-info" onclick="verMascotas(<?php echo $row['id_cliente']; ?>)" 
                                                    data-bs-toggle="tooltip" title="Ver mascotas">
                                                <i class="fas fa-paw"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="eliminarCliente(<?php echo $row['id_cliente']; ?>)" 
                                                    data-bs-toggle="tooltip" title="Eliminar cliente">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                        
                                        <!-- Botones para móviles -->
                                        <div class="d-flex d-md-none flex-column gap-1">
                                            <button class="btn btn-sm btn-outline-primary w-100" onclick="editarCliente(<?php echo $row['id_cliente']; ?>)">
                                                <i class="fas fa-edit me-1"></i> Editar
                                            </button>
                                            <button class="btn btn-sm btn-outline-info w-100" onclick="verMascotas(<?php echo $row['id_cliente']; ?>)">
                                                <i class="fas fa-paw me-1"></i> Mascotas
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger w-100" onclick="eliminarCliente(<?php echo $row['id_cliente']; ?>)">
                                                <i class="fas fa-trash me-1"></i> Eliminar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginación -->
                    <?php echo $pagination->generatePaginationHTML($paginationInfo, '#/clientes'); ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-4x text-muted mb-3"></i>
                        <h4>No se encontraron resultados</h4>
                        <p class="text-muted">No hay clientes que coincidan con tu búsqueda</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Lista Completa de Clientes (cuando no hay búsqueda) -->
    <?php if (!isset($_GET['buscar_nombre'])): ?>
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Todos los Clientes</h5>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-success" onclick="descargarExcel('clientes')">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($clientes) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Cliente</th>
                                <th>Contacto</th>
                                <th>DNI</th>
                                <th>Dirección</th>
                                <th width="180" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientes as $row): ?>
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
                                        <button class="btn btn-outline-danger" onclick="eliminarCliente(<?php echo $row['id_cliente']; ?>)" 
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
                <?php echo $pagination->generatePaginationHTML($paginationInfo, '#/clientes'); ?>
                
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
                    <form id="formNuevoCliente">
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

<!-- Modal para Editar Cliente -->
<div class="modal fade" id="editarClienteModal" tabindex="-1" aria-labelledby="editarClienteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" id="contenidoModalEditar">
            <!-- El contenido se carga dinámicamente desde editar_cliente.php -->
        </div>
    </div>
</div>

<script>
// Funciones para los botones de acción
function editarCliente(id) {
    // Resetear el contenido del modal con loading
    $('#contenidoModalEditar').html(`
        <div class="modal-body text-center p-5">
            <div class="d-flex flex-column align-items-center">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <h5 class="text-muted">Cargando información del cliente...</h5>
                <p class="text-muted small mb-0">Por favor, espera un momento</p>
            </div>
        </div>
    `);
    
    // Mostrar el modal inmediatamente
    $('#editarClienteModal').modal('show');
    
    $.ajax({
        url: 'modules/editar_cliente.php',
        method: 'GET',
        data: { id: id },
        timeout: 10000, // 10 segundos de timeout
        success: function(response) {
            $('#contenidoModalEditar').html(response);
        },
        error: function(xhr, status, error) {
            let errorMessage = 'No se pudo cargar el formulario de edición';
            
            if (status === 'timeout') {
                errorMessage = 'La solicitud tardó demasiado tiempo. Verifica tu conexión.';
            } else if (xhr.status === 404) {
                errorMessage = 'Cliente no encontrado.';
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
                            <button type="button" class="btn btn-primary" onclick="editarCliente(${id})">
                                <i class="fas fa-refresh me-1"></i> Reintentar
                            </button>
                        </div>
                    </div>
                </div>
            `);
        }
    });
}

function verMascotas(id) {
    console.log('Viendo mascotas del cliente ID:', id);
    
    // Mostrar loading
    Swal.fire({
        title: 'Cargando...',
        text: 'Obteniendo mascotas del cliente',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });
    
    $.ajax({
        url: 'modules/mascotas.php',
        method: 'GET',
        data: { cliente_id: id },
        success: function(response) {
            Swal.close();
            $('#contenido').html(response);
        },
        error: function() {
            Swal.close();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error al cargar las mascotas del cliente',
                confirmButtonColor: '#7c4dff'
            });
        }
    });
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

function descargarExcel(tabla) {
    window.open('modules/exportar_excel.php?tabla=' + tabla, '_blank');
}

function eliminarCliente(id) {
    // Primero obtener información del cliente
    $.ajax({
        url: 'modules/editar_cliente.php',
        method: 'GET',
        data: { id: id, info_only: true },
        dataType: 'json',
        success: function(cliente) {
            Swal.fire({
                title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i>¿Eliminar cliente?',
                html: `
                    <div class="text-start">
                        <p class="mb-2">Estás a punto de eliminar al cliente:</p>
                        <div class="card bg-light border-warning">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0 me-3">
                                        <div class="bg-white rounded-circle p-2 text-center" style="width: 45px; height: 45px;">
                                            <i class="fas fa-user text-primary" style="font-size: 16px;"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="card-title mb-1">
                                            <strong>${cliente.nombre} ${cliente.apellido}</strong>
                                        </h6>
                                        <div class="text-muted small">
                                            <div><strong>Celular:</strong> ${cliente.celular}</div>
                                            ${cliente.dni ? `<div><strong>DNI:</strong> ${cliente.dni}</div>` : ''}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-warning mt-3 mb-0 d-flex align-items-center">
                            <i class="fas fa-info-circle me-2"></i>
                            <small><strong>Advertencia:</strong> Esta acción eliminará al cliente y todas sus mascotas asociadas. No se puede deshacer.</small>
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
                        title: 'Eliminando cliente...',
                        html: '<div class="d-flex justify-content-center"><div class="spinner-border text-danger" role="status"><span class="visually-hidden">Cargando...</span></div></div>',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        customClass: {
                            popup: 'swal-responsive'
                        }
                    });
                    
                    $.ajax({
                        url: 'modules/eliminar_cliente.php',
                        method: 'POST',
                        data: { id: id },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                let mensaje = response.message || 'Cliente eliminado correctamente';
                                
                                if (response.pets_count > 0) {
                                    mensaje += `\n\nSe eliminaron también ${response.pets_count} mascota(s): ${response.deleted_pets.join(', ')}`;
                                }
                                
                                Swal.fire({
                                    title: '¡Eliminado!',
                                    html: `
                                        <div class="text-center">
                                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                            <p class="mb-0">El cliente ha sido eliminado correctamente.</p>
                                        </div>
                                    `,
                                    icon: 'success',
                                    confirmButtonColor: '#28a745',
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
                                            <p class="mb-0">${response.message || 'No se pudo eliminar el cliente'}</p>
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
                        error: function(xhr, status, error) {
                            console.error('Error AJAX:', {
                                xhr: xhr,
                                status: status,
                                error: error,
                                responseText: xhr.responseText
                            });
                            
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
        },
        error: function() {
            // Fallback si no se puede obtener info del cliente
            Swal.fire({
                title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i>¿Eliminar cliente?',
                html: `
                    <div class="text-start">
                        <p class="mb-2">Estás a punto de eliminar este cliente.</p>
                        <div class="alert alert-warning mt-3 mb-0 d-flex align-items-center">
                            <i class="fas fa-info-circle me-2"></i>
                            <small><strong>Advertencia:</strong> Esta acción no se puede deshacer.</small>
                        </div>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-trash me-1"></i> Sí, eliminar',
                cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
                customClass: {
                    popup: 'swal-responsive'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar loading
                    Swal.fire({
                        title: 'Eliminando cliente...',
                        html: '<div class="d-flex justify-content-center"><div class="spinner-border text-danger" role="status"><span class="visually-hidden">Cargando...</span></div></div>',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        customClass: {
                            popup: 'swal-responsive'
                        }
                    });
                    
                    $.ajax({
                        url: 'modules/eliminar_cliente.php',
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
                                            <p class="mb-0">El cliente ha sido eliminado correctamente.</p>
                                        </div>
                                    `,
                                    icon: 'success',
                                    confirmButtonColor: '#28a745',
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
                                            <p class="mb-0">${response.message || 'No se pudo eliminar el cliente'}</p>
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
    });
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
    
    // Manejar envío del formulario de nuevo cliente
    $('#formNuevoCliente').on('submit', function(e) {
        e.preventDefault();
        
        // Prevenir múltiples envíos
        if ($(this).data('submitting')) {
            return false;
        }
        $(this).data('submitting', true);
        
        // Validar campos obligatorios
        if (!$('#nombre').val().trim()) {
            $(this).data('submitting', false);
            return;
        }
        
        if (!$('#apellido').val().trim()) {
            $(this).data('submitting', false);
            return;
        }
        
        if (!$('#celular').val().trim()) {
            $(this).data('submitting', false);
            return;
        }
        
        // Enviar datos via AJAX
        const formData = new FormData();
        formData.append('nombre', $('#nombre').val().trim());
        formData.append('apellido', $('#apellido').val().trim());
        formData.append('celular', $('#celular').val().trim());
        formData.append('dni', $('#dni').val().trim());
        formData.append('direccion', $('#direccion').val().trim());
        
        $.ajax({
            url: 'modules/clientes.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                $('#formNuevoCliente').data('submitting', false);
                if (response.success) {
                    // Cerrar modal
                    $('#nuevoClienteModal').modal('hide');
                    
                    // Limpiar formulario
                    $('#formNuevoCliente')[0].reset();
                    
                    // Recargar solo el contenido de clientes
                    $.ajax({
                        url: 'modules/clientes.php',
                        method: 'GET',
                        success: function(data) {
                            $('#contenido').html(data);
                        },
                        error: function() {
                            // Si falla, recargar toda la página
                            window.location.reload();
                        }
                    });
                }
            },
            error: function(xhr, status, error) {
                $('#formNuevoCliente').data('submitting', false);
                // Solo cerrar el modal y limpiar, sin mostrar mensaje de error
                $('#nuevoClienteModal').modal('hide');
                $('#formNuevoCliente')[0].reset();
            }
        });
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
    
    .table {
        margin-bottom: 0;
    }
    
    .table td {
        border: none;
        padding: 0.5rem 0.25rem;
    }
    
    /* Mejorar la vista mobile de clientes */
    .d-table-cell.d-sm-none .card {
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: all 0.2s ease;
    }
    
    .d-table-cell.d-sm-none .card:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        transform: translateY(-1px);
    }
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

/* Mejoras para badges */
.badge {
    font-size: 0.7em;
    padding: 0.35em 0.65em;
}

@media (max-width: 576px) {
    .badge {
        font-size: 0.6em;
        padding: 0.25em 0.5em;
    }
}
</style>