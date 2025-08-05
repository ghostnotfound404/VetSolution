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

// Procesar el registro de nueva mascota
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar que todos los campos requeridos estén presentes
    if (isset($_POST['id_cliente']) && isset($_POST['nombre']) && isset($_POST['fecha_nacimiento']) && 
        isset($_POST['especie']) && isset($_POST['genero'])) {
        
        $id_cliente = intval($_POST['id_cliente']);
        $nombre = trim($_POST['nombre']);
        $fecha_nacimiento = $_POST['fecha_nacimiento'];
        $especie = $_POST['especie'];
        $raza = isset($_POST['raza']) && !empty($_POST['raza']) ? $_POST['raza'] : 'Mestizo';
        $genero = $_POST['genero'];
        $esterilizado = isset($_POST['esterilizado']) ? $_POST['esterilizado'] : 'No';
        
        // Validar que el cliente existe
        $query_cliente = "SELECT id_cliente FROM clientes WHERE id_cliente = ?";
        $stmt_cliente = $conn->prepare($query_cliente);
        $stmt_cliente->bind_param("i", $id_cliente);
        $stmt_cliente->execute();
        $result_cliente = $stmt_cliente->get_result();
        
        if ($result_cliente->num_rows > 0) {
            // Insertar la mascota
            $query_mascota = "INSERT INTO mascotas (id_cliente, nombre, fecha_nacimiento, especie, raza, genero, esterilizado, estado) VALUES (?, ?, ?, ?, ?, ?, ?, 'Activo')";
            $stmt_mascota = $conn->prepare($query_mascota);
            $stmt_mascota->bind_param("issssss", $id_cliente, $nombre, $fecha_nacimiento, $especie, $raza, $genero, $esterilizado);
            
            if ($stmt_mascota->execute()) {
                // Registro exitoso - respuesta JSON
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Mascota registrada correctamente']);
                exit();
            } else {
                // Error en la inserción
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error al registrar la mascota: ' . $stmt_mascota->error]);
                exit();
            }
            $stmt_mascota->close();
        } else {
            // Cliente no existe
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El cliente seleccionado no existe']);
            exit();
        }
        $stmt_cliente->close();
    } else {
        // Campos faltantes
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Todos los campos obligatorios deben ser completados']);
        exit();
    }
}

// Aplicar filtros si existe el parámetro filtro
$where_clause = "";
// Obtener mascotas con paginación
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : '';
$where_conditions = [];

// Aplicar filtros
switch ($filtro) {
    case 'caninos':
        $where_conditions[] = "m.especie = 'Canino'";
        break;
    case 'felinos':
        $where_conditions[] = "m.especie = 'Felino'";
        break;
    case 'esterilizados':
        $where_conditions[] = "m.esterilizado = 'Si'";
        break;
    case 'no_esterilizados':
        $where_conditions[] = "m.esterilizado = 'No'";
        break;
}

$where_clause = !empty($where_conditions) ? implode(' AND ', $where_conditions) : '';
$joins = "INNER JOIN clientes c ON m.id_cliente = c.id_cliente";

// Si hay búsqueda, usar searchWithPagination
if (!empty($buscar)) {
    $search_fields = ['m.nombre', 'c.nombre', 'c.apellido', 'm.raza', 'm.especie'];
    $pagination_result = $pagination->searchWithPagination(
        'mascotas m', 
        $search_fields, 
        $buscar, 
        'm.*, c.nombre as nombre_cliente, c.apellido as apellido_cliente', 
        $where_clause, 
        'm.id_mascota DESC'
    );
} else {
    $pagination_result = $pagination->getPaginatedData(
        'mascotas m', 
        'm.*, c.nombre as nombre_cliente, c.apellido as apellido_cliente', 
        $joins, 
        $where_clause, 
        'm.id_mascota DESC'
    );
}

$mascotas = $pagination_result['data'];
$total_pages = $pagination_result['pagination']['total_pages'] ?? 1;
$current_page = $pagination_result['pagination']['current_page'] ?? 1;
$total_records = $pagination_result['pagination']['total_records'] ?? 0;

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
    </div>

    <!-- Tarjetas de Resumen -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Total Mascotas</h6>
                            <h4 class="mb-0" style="color: #fff;"><?php echo $stats['total_mascotas']; ?></h4>
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
                            <h4 class="mb-0" style="color: #fff;"><?php echo $stats['caninos']; ?></h4>
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
                            <h4 class="mb-0" style="color: #fff;"><?php echo $stats['felinos']; ?></h4>
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
                            <h4 class="mb-0" style="color: #fff;"><?php echo $stats['esterilizados']; ?></h4>
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
                        <i class="fas fa-sliders-h"></i> Filtros
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

    <!-- Lista de Mascotas con Paginación -->
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>
                <?php if (!empty($buscar)): ?>
                    Resultados para: "<?php echo htmlspecialchars($buscar); ?>"
                <?php else: ?>
                    Todas las Mascotas
                <?php endif; ?>
            </h5>
            <div class="d-flex align-items-center">
                <?php if (!empty($buscar)): ?>
                    <span class="badge bg-primary me-2"><?php echo $total_records; ?> encontrados</span>
                <?php endif; ?>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="descargarExcel('mascotas')">
                        <i class="fas fa-file-excel me-1"></i> Excel
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($mascotas) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="25%" class="d-none d-sm-table-cell">Mascota</th>
                                <th width="100%" class="d-table-cell d-sm-none">Información de la Mascota</th>
                                <th width="20%" class="d-none d-md-table-cell">Especie/Raza</th>
                                <th width="20%" class="d-none d-lg-table-cell">Propietario</th>
                                <th width="15%" class="d-none d-lg-table-cell">Edad</th>
                                <th width="15%" class="d-none d-md-table-cell">Estado</th>
                                <th width="15%" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mascotas as $row): ?>
                            <tr>
                                <!-- Vista Desktop - Mascota -->
                                <td class="d-none d-sm-table-cell">
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
                                
                                <!-- Vista Mobile - Información Completa -->
                                <td class="d-table-cell d-sm-none">
                                    <div class="card border-0 bg-light">
                                        <div class="card-body p-3">
                                            <div class="d-flex align-items-start mb-2">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="bg-white rounded-circle p-2 text-center" style="width: 45px; height: 45px;">
                                                        <i class="fas <?php echo $row['especie'] == 'Felino' ? 'fa-cat' : 'fa-dog'; ?> text-primary" style="font-size: 16px;"></i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($row['nombre']); ?></h6>
                                                    <div class="row g-2 text-sm">
                                                        <div class="col-6">
                                                            <span class="text-muted">Especie:</span>
                                                            <div><span class="badge bg-<?php echo $row['especie'] == 'Canino' ? 'success' : 'info'; ?> rounded-pill"><?php echo htmlspecialchars($row['especie']); ?></span></div>
                                                        </div>
                                                        <div class="col-6">
                                                            <span class="text-muted">Género:</span>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($row['genero']); ?></div>
                                                        </div>
                                                    </div>
                                                    <div class="row g-2 text-sm mt-1">
                                                        <div class="col-6">
                                                            <span class="text-muted">Raza:</span>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($row['raza']); ?></div>
                                                        </div>
                                                        <div class="col-6">
                                                            <span class="text-muted">Edad:</span>
                                                            <div class="fw-bold">
                                                                <?php 
                                                                    $fechaNac = new DateTime($row['fecha_nacimiento']);
                                                                    $hoy = new DateTime();
                                                                    $edad = $hoy->diff($fechaNac);
                                                                    echo $edad->y . 'a ' . $edad->m . 'm';
                                                                ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="mt-2">
                                                        <div class="d-flex align-items-center justify-content-between">
                                                            <div>
                                                                <span class="text-muted small">Propietario:</span>
                                                                <div class="fw-bold small"><?php echo htmlspecialchars($row['nombre_cliente'] . ' ' . $row['apellido_cliente']); ?></div>
                                                            </div>
                                                            <div class="d-flex flex-column align-items-end">
                                                                <span class="badge bg-<?php echo $row['estado'] == 'Activo' ? 'success' : 'secondary'; ?> rounded-pill">
                                                                    <?php echo htmlspecialchars($row['estado']); ?>
                                                                </span>
                                                                <?php if ($row['esterilizado'] == 'Si'): ?>
                                                                    <small class="text-success mt-1"><i class="fas fa-check-circle"></i> Esterilizado</small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <span class="badge bg-<?php echo $row['especie'] == 'Canino' ? 'success' : 'info'; ?>"><?php echo htmlspecialchars($row['especie']); ?></span>
                                    <div class="small text-muted"><?php echo htmlspecialchars($row['raza']); ?></div>
                                </td>
                                <td class="d-none d-lg-table-cell"><?php echo htmlspecialchars($row['nombre_cliente'] . ' ' . $row['apellido_cliente']); ?></td>
                                <td class="d-none d-lg-table-cell">
                                    <?php 
                                        $fechaNac = new DateTime($row['fecha_nacimiento']);
                                        $hoy = new DateTime();
                                        $edad = $hoy->diff($fechaNac);
                                        echo $edad->y . ' años, ' . $edad->m . ' meses';
                                    ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <span class="badge bg-<?php echo $row['estado'] == 'Activo' ? 'success' : 'secondary'; ?>">
                                        <?php echo htmlspecialchars($row['estado']); ?>
                                    </span>
                                    <?php if ($row['esterilizado'] == 'Si'): ?>
                                        <div class="small text-muted"><i class="fas fa-check-circle text-success"></i> Esterilizado</div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group d-none d-md-flex" role="group">
                                        <button class="btn btn-sm btn-outline-primary" onclick="editarMascota(<?php echo $row['id_mascota']; ?>)" 
                                               data-bs-toggle="tooltip" title="Editar mascota">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-info" onclick="verHistoria(<?php echo $row['id_mascota']; ?>)" 
                                               data-bs-toggle="tooltip" title="Historia clínica">
                                            <i class="fas fa-file-medical"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="verDetalles(<?php echo $row['id_mascota']; ?>)" 
                                               data-bs-toggle="tooltip" title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="eliminarMascota(<?php echo $row['id_mascota']; ?>)" 
                                               data-bs-toggle="tooltip" title="Eliminar mascota">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Botones para móviles -->
                                    <div class="d-flex d-md-none flex-column gap-1">
                                        <button class="btn btn-sm btn-outline-primary w-100" onclick="editarMascota(<?php echo $row['id_mascota']; ?>)">
                                            <i class="fas fa-edit me-1"></i> Editar
                                        </button>
                                        <button class="btn btn-sm btn-outline-info w-100" onclick="verHistoria(<?php echo $row['id_mascota']; ?>)">
                                            <i class="fas fa-file-medical me-1"></i> Historia
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger w-100" onclick="eliminarMascota(<?php echo $row['id_mascota']; ?>)">
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
                <?php echo $pagination->generatePaginationHTML($pagination_result['pagination'], '#/mascotas'); ?>
                
            <?php else: ?>
                <div class="text-center py-5">
                    <?php if (!empty($buscar)): ?>
                        <i class="fas fa-search fa-4x text-muted mb-3"></i>
                        <h4>No se encontraron resultados</h4>
                        <p class="text-muted">No hay mascotas que coincidan con tu búsqueda</p>
                    <?php else: ?>
                        <i class="fas fa-paw fa-4x text-muted mb-3"></i>
                        <h4>No hay mascotas registradas</h4>
                        <p class="text-muted">Comienza registrando nuevas mascotas haciendo clic en el botón "Nueva Mascota"</p>
                        <button type="button" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#nuevaMascotaModal">
                            <i class="fas fa-plus-circle me-1"></i> Registrar Mascota
                        </button>
                    <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

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
                    <form id="formNuevaMascota">
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
    // Resetear el contenido del modal con loading
    $('#contenidoModalEditar').html(`
        <div class="modal-body text-center p-5">
            <div class="d-flex flex-column align-items-center">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <h5 class="text-muted">Cargando información de la mascota...</h5>
                <p class="text-muted small mb-0">Por favor, espera un momento</p>
            </div>
        </div>
    `);
    
    // Mostrar el modal inmediatamente
    $('#editarMascotaModal').modal('show');
    
    $.ajax({
        url: 'modules/editar_mascota.php',
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
                errorMessage = 'Mascota no encontrada.';
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
                            <button type="button" class="btn btn-primary" onclick="editarMascota(${id})">
                                <i class="fas fa-refresh me-1"></i> Reintentar
                            </button>
                        </div>
                    </div>
                </div>
            `);
        }
    });
}

function verHistoria(id) {
    console.log('Viendo historia de mascota ID:', id);
    
    // Mostrar loading
    Swal.fire({
        title: 'Cargando...',
        text: 'Obteniendo historia clínica',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });
    
    $.ajax({
        url: 'modules/historia_clinica.php',
        method: 'GET',
        data: { id_mascota: id },
        success: function(response) {
            Swal.close();
            $('#contenido').html(response);
        },
        error: function() {
            Swal.close();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error al cargar la historia clínica',
                confirmButtonColor: '#7c4dff'
            });
        }
    });
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

function descargarExcel(tabla) {
    window.open('modules/exportar_excel.php?tabla=' + tabla, '_blank');
}

function eliminarMascota(id) {
    // Primero obtener información de la mascota
    $.ajax({
        url: 'modules/editar_mascota.php',
        method: 'GET',
        data: { id: id, info_only: true },
        dataType: 'json',
        success: function(mascota) {
            Swal.fire({
                title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i>¿Eliminar mascota?',
                html: `
                    <div class="text-start">
                        <p class="mb-2">Estás a punto de eliminar la mascota:</p>
                        <div class="card bg-light border-warning">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0 me-3">
                                        <div class="bg-white rounded-circle p-2 text-center" style="width: 45px; height: 45px;">
                                            <i class="fas ${mascota.especie === 'Felino' ? 'fa-cat' : 'fa-dog'} text-primary" style="font-size: 16px;"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="card-title mb-1">
                                            <strong>${mascota.nombre}</strong>
                                        </h6>
                                        <div class="text-muted small">
                                            <div><strong>Especie:</strong> ${mascota.especie} - ${mascota.raza}</div>
                                            <div><strong>Propietario:</strong> ${mascota.propietario}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-warning mt-3 mb-0 d-flex align-items-center">
                            <i class="fas fa-info-circle me-2"></i>
                            <small><strong>Advertencia:</strong> Esta acción eliminará toda la información y el historial médico de la mascota. No se puede deshacer.</small>
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
                        title: 'Eliminando mascota...',
                        html: '<div class="d-flex justify-content-center"><div class="spinner-border text-danger" role="status"><span class="visually-hidden">Cargando...</span></div></div>',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        customClass: {
                            popup: 'swal-responsive'
                        }
                    });
                    
                    $.ajax({
                        url: 'modules/eliminar_mascota.php',
                        method: 'POST',
                        data: { id_mascota: id },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    title: '¡Eliminada!',
                                    html: `
                                        <div class="text-center">
                                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                            <p class="mb-0">La mascota ha sido eliminada correctamente.</p>
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
                                            <p class="mb-0">${response.message || 'No se pudo eliminar la mascota'}</p>
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
        },
        error: function() {
            // Fallback si no se puede obtener info de la mascota
            Swal.fire({
                title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i>¿Eliminar mascota?',
                html: `
                    <div class="text-start">
                        <p class="mb-2">Estás a punto de eliminar esta mascota.</p>
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
                    // Continuar con eliminación...
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
        'Felino': ['Persa', 'Siamés', 'Maine Coon', 'Británico', 'Ragdoll', 'Bengalí', 'Abisinio', 'Mestizo']
    };
    
    const select = $('#raza');
    select.html('<option value="">Seleccionar...</option>');
    
    if (razas[especie]) {
        razas[especie].forEach(function(raza) {
            select.append('<option value="' + raza + '">' + raza + '</option>');
        });
    }
}

// Manejar envío del formulario de nueva mascota
$(document).ready(function() {
    // Inicializar validaciones para formulario de edición
    $(document).on('submit', '#formEditarMascota', function(e) {
        e.preventDefault();
        
        // Validar campos obligatorios
        if (!$('#id_cliente_editar').val()) {
            Swal.fire('Error', 'Debe seleccionar un propietario', 'error');
            return;
        }
        
        if (!$('#nombre_mascota_editar').val().trim()) {
            Swal.fire('Error', 'El nombre de la mascota es obligatorio', 'error');
            return;
        }
        
        if (!$('#fecha_nacimiento_editar').val()) {
            Swal.fire('Error', 'La fecha de nacimiento es obligatoria', 'error');
            return;
        }
        
        if (!$('#especie_editar').val()) {
            Swal.fire('Error', 'Debe seleccionar una especie', 'error');
            return;
        }
        
        if (!$('#genero_editar').val()) {
            Swal.fire('Error', 'Debe seleccionar el género', 'error');
            return;
        }
        
        // Enviar datos vía AJAX
        const formData = new FormData(this);
        
        // Debug - mostrar datos que se están enviando
        console.log('ID Cliente:', $('#id_cliente_editar').val());
        console.log('Nombre:', $('#nombre_mascota_editar').val());
        console.log('Fecha Nacimiento:', $('#fecha_nacimiento_editar').val());
        console.log('Especie:', $('#especie_editar').val());
        
        $.ajax({
            url: 'modules/editar_mascota.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                console.log('Respuesta del servidor:', response);
                if (response.success) {
                    // Cerrar modal
                    $('#editarMascotaModal').modal('hide');
                    
                    // Mostrar mensaje de éxito
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: 'Mascota actualizada correctamente',
                        confirmButtonText: 'Aceptar'
                    }).then(() => {
                        // Recargar solo el contenido de mascotas
                        $('#contenido').load('modules/mascotas.php');
                    });
                } else {
                    Swal.fire('Error', response.message || 'Error al actualizar la mascota', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error en la solicitud AJAX:', status, error);
                console.log('Respuesta del servidor:', xhr.responseText);
                Swal.fire('Error', 'Ocurrió un error al procesar la solicitud. Consulte la consola para más detalles.', 'error');
            }
        });
    });

    $('#formNuevaMascota').on('submit', function(e) {
        e.preventDefault();
        
        // Validar campos obligatorios
        if (!$('#id_cliente').val()) {
            Swal.fire('Error', 'Debe seleccionar un propietario', 'error');
            return;
        }
        
        if (!$('#nombre_mascota').val().trim()) {
            Swal.fire('Error', 'El nombre de la mascota es obligatorio', 'error');
            return;
        }
        
        if (!$('#fecha_nacimiento').val()) {
            Swal.fire('Error', 'La fecha de nacimiento es obligatoria', 'error');
            return;
        }
        
        if (!$('#especie').val()) {
            Swal.fire('Error', 'Debe seleccionar una especie', 'error');
            return;
        }
        
        if (!$('#genero').val()) {
            Swal.fire('Error', 'Debe seleccionar el género', 'error');
            return;
        }
        
        // Enviar datos via AJAX
        const formData = new FormData();
        formData.append('id_cliente', $('#id_cliente').val());
        formData.append('nombre', $('#nombre_mascota').val().trim());
        formData.append('fecha_nacimiento', $('#fecha_nacimiento').val());
        formData.append('especie', $('#especie').val());
        formData.append('raza', $('#raza').val() || 'Mestizo');
        formData.append('genero', $('#genero').val());
        formData.append('esterilizado', $('#esterilizado').val());
        
        $.ajax({
            url: 'modules/mascotas.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Cerrar modal
                    $('#nuevaMascotaModal').modal('hide');
                    
                    // Limpiar formulario
                    $('#formNuevaMascota')[0].reset();
                    $('#resultados_propietario').html('');
                    $('#id_cliente').val('');
                    
                    // Recargar solo el contenido de mascotas
                    $.ajax({
                        url: 'modules/mascotas.php',
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
                // Solo cerrar el modal y limpiar, sin mostrar mensaje de error
                $('#nuevaMascotaModal').modal('hide');
                $('#formNuevaMascota')[0].reset();
                $('#resultados_propietario').html('');
                $('#id_cliente').val('');
            }
        });
    });
    
    // Inicializar tooltips de Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<!-- Modal para Editar Mascota -->
<div class="modal fade" id="editarMascotaModal" tabindex="-1" aria-labelledby="editarMascotaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="editarMascotaModalLabel">
                    <i class="fas fa-edit me-2"></i> Editar Mascota
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="contenidoModalEditar">
                <!-- Aquí se cargará el contenido dinámicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancelar
                </button>
                <button type="submit" form="formEditarMascota" class="btn btn-warning">
                    <i class="fas fa-save me-1"></i> Guardar Cambios
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Detalles de Mascota -->
<div class="modal fade" id="detallesMascotaModal" tabindex="-1" aria-labelledby="detallesMascotaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="detallesMascotaModalLabel">
                    <i class="fas fa-paw me-2"></i> 
                    <span class="d-none d-sm-inline">Detalles de la Mascota</span>
                    <span class="d-inline d-sm-none">Detalles</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="contenidoModalDetalles">
                <!-- Aquí se cargará el contenido dinámicamente -->
            </div>
            <div class="modal-footer flex-column flex-sm-row p-3">
                <button type="button" class="btn btn-secondary w-100 w-sm-auto" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

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
    
    /* Mejorar la vista mobile de mascotas */
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

<script>
$(document).ready(function() {
    // Inicializar tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
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