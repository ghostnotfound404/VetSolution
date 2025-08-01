<?php
include('../includes/config.php');

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

// Obtener clientes (con búsqueda si se especifica)
if (isset($_GET['buscar_nombre']) && !empty(trim($_GET['buscar_nombre']))) {
    $buscar_nombre = trim($_GET['buscar_nombre']);
    
    $search_sql = "SELECT * FROM clientes 
                  WHERE nombre LIKE ? 
                     OR apellido LIKE ? 
                     OR dni LIKE ?
                     OR CONCAT(nombre, ' ', apellido) LIKE ?
                  ORDER BY fecha_registro DESC";
    
    $stmt = $conn->prepare($search_sql);
    $searchTerm = "%$buscar_nombre%";
    $stmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    // Obtener todos los clientes ordenados por fecha de registro (más recientes primero)
    $sql = "SELECT * FROM clientes ORDER BY fecha_registro DESC";
    $result = $conn->query($sql);
}

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
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-search-result me-2"></i>
                    Resultados para: "<?php echo htmlspecialchars($_GET['buscar_nombre']); ?>"
                </h5>
                <span class="badge bg-primary"><?php echo $result->num_rows; ?> encontrados</span>
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
                                    <th width="180" class="text-center">Acciones</th>
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
                                            <button class="btn btn-outline-danger" onclick="eliminarCliente(<?php echo $row['id_cliente']; ?>)" 
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
            <?php if ($result->num_rows > 0): ?>
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
                                        <button class="btn btn-outline-danger" onclick="eliminarCliente(<?php echo $row['id_cliente']; ?>)" 
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

function descargarExcel(tabla) {
    window.open('modules/exportar_excel.php?tabla=' + tabla, '_blank');
}

function eliminarCliente(id) {
    console.log('Función eliminarCliente llamada con ID:', id);
    
    // Verificar si SweetAlert2 está disponible
    if (typeof Swal === 'undefined') {
        alert('SweetAlert2 no está disponible');
        return;
    }
    
    Swal.fire({
        title: '¿Estás seguro?',
        text: "Esta acción no se puede deshacer",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e53e3e',
        cancelButtonColor: '#3182ce',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        console.log('Resultado del SweetAlert:', result);
        if (result.isConfirmed) {
            console.log('Usuario confirmó eliminación, enviando AJAX...');
            
            // Mostrar loading
            Swal.fire({
                title: 'Eliminando...',
                text: 'Por favor espere',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
            
            $.ajax({
                url: 'modules/eliminar_cliente.php',
                method: 'POST',
                data: { id: id },
                dataType: 'json',
                timeout: 10000, // 10 segundos timeout
                beforeSend: function() {
                    console.log('Enviando petición AJAX a:', 'modules/eliminar_cliente.php');
                    console.log('Datos:', { id: id });
                },
                success: function(response) {
                    console.log('Respuesta AJAX exitosa:', response);
                    Swal.close(); // Cerrar loading
                    
                    if (response && response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: response.message || 'Cliente eliminado correctamente',
                            confirmButtonColor: '#7c4dff',
                            confirmButtonText: 'Aceptar'
                        }).then(() => {
                            // Recargar la lista
                            location.reload(); // Recargar toda la página por ahora
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Error desconocido',
                            confirmButtonColor: '#7c4dff',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error AJAX completo:', {
                        xhr: xhr,
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    
                    Swal.close(); // Cerrar loading
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de conexión',
                        text: 'Error: ' + error + ' - Status: ' + status,
                        confirmButtonColor: '#7c4dff',
                        confirmButtonText: 'OK'
                    });
                }
            });
        } else {
            console.log('Usuario canceló la eliminación');
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
        
        // Validar campos obligatorios
        if (!$('#nombre').val().trim()) {
            return;
        }
        
        if (!$('#apellido').val().trim()) {
            return;
        }
        
        if (!$('#celular').val().trim()) {
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
});
</script>