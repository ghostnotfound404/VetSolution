<?php
include('../includes/config.php');

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
if (isset($_GET['filtro'])) {
    $filtro = $_GET['filtro'];
    
    switch ($filtro) {
        case 'caninos':
            $where_clause = "WHERE m.especie = 'Canino'";
            break;
        case 'felinos':
            $where_clause = "WHERE m.especie = 'Felino'";
            break;
        case 'esterilizados':
            $where_clause = "WHERE m.esterilizado = 'Si'";
            break;
        case 'no_esterilizados':
            $where_clause = "WHERE m.esterilizado = 'No'";
            break;
        default:
            $where_clause = "";
    }
}

// Obtener todas las mascotas con información del cliente
$sql = "SELECT m.*, c.nombre as nombre_cliente, c.apellido as apellido_cliente 
        FROM mascotas m 
        INNER JOIN clientes c ON m.id_cliente = c.id_cliente 
        $where_clause
        ORDER BY m.id_mascota DESC";
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
                      ORDER BY m.id_mascota DESC";
        
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
                                        <span class="badge bg-<?php echo $row['especie'] == 'Canino' ? 'success' : 'info'; ?>"><?php echo htmlspecialchars($row['especie']); ?></span>
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
                                                   data-bs-toggle="tooltip" title="Historia">
                                                <i class="fas fa-file-medical"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary" onclick="verDetalles(<?php echo $row['id_mascota']; ?>)" 
                                                   data-bs-toggle="tooltip" title="Ver">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="eliminarMascota(<?php echo $row['id_mascota']; ?>)" 
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
                <button type="button" class="btn btn-sm btn-outline-success" onclick="descargarExcel('mascotas')">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if ($result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
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
                                    <span class="badge bg-<?php echo $row['especie'] == 'Canino' ? 'success' : 'info'; ?>"><?php echo htmlspecialchars($row['especie']); ?></span>
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
                                               data-bs-toggle="tooltip" title="Historia">
                                            <i class="fas fa-file-medical"></i>
                                        </button>
                                        <button class="btn btn-outline-secondary" onclick="verDetalles(<?php echo $row['id_mascota']; ?>)" 
                                               data-bs-toggle="tooltip" title="Ver">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="eliminarMascota(<?php echo $row['id_mascota']; ?>)" 
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

function descargarExcel(tabla) {
    window.open('modules/exportar_excel.php?tabla=' + tabla, '_blank');
}

function eliminarMascota(id) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "Esta acción no se puede revertir",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'modules/eliminar_mascota.php',
                method: 'POST',
                data: { id_mascota: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire(
                            '¡Eliminado!',
                            response.message,
                            'success'
                        ).then(() => {
                            // Recargar la página para actualizar la lista
                            $('#contenido').load('modules/mascotas.php');
                        });
                    } else {
                        Swal.fire(
                            'Error',
                            response.message,
                            'error'
                        );
                    }
                },
                error: function() {
                    Swal.fire(
                        'Error',
                        'Ocurrió un error al procesar la solicitud',
                        'error'
                    );
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
                    <i class="fas fa-paw me-2"></i> Detalles de la Mascota
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="contenidoModalDetalles">
                <!-- Aquí se cargará el contenido dinámicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</div>