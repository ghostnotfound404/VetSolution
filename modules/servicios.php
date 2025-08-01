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
        $insert_sql = "INSERT INTO servicios (nombre, precio, descripcion) VALUES (?, ?, ?)";
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
    $sql = "SELECT * FROM servicios ORDER BY nombre";
    $result = $conn->query($sql);
}
?>

<div class="container-fluid px-4 servicios">
    <!-- Encabezado con estadísticas -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-concierge-bell me-2"></i>Gestión de Servicios</h2>
    </div>

    <!-- Tarjeta de Búsqueda - Versión corregida -->
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
                               placeholder="Buscar por nombre, descripción..." 
                               value="<?php echo isset($_GET['buscar_servicio']) ? htmlspecialchars($_GET['buscar_servicio']) : ''; ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Buscar
                        </button>
                        <?php if (isset($_GET['buscar_servicio'])): ?>
                        <button type="button" class="btn btn-outline-secondary" onclick="limpiarBusquedaServicio()">
                            <i class="fas fa-times me-1"></i> Limpiar
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4 d-flex align-items-stretch">
                    <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#nuevoServicioModal">
                        <i class="fas fa-plus-circle me-1"></i> Nuevo Servicio
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Resultados de Búsqueda -->
    <?php if (isset($_GET['buscar_servicio']) && !empty(trim($_GET['buscar_servicio']))): ?>
        <?php
        $buscar_servicio = trim($_GET['buscar_servicio']);
        
        $search_sql = "SELECT * FROM servicios 
                      WHERE nombre LIKE ? 
                         OR descripcion LIKE ?
                      ORDER BY nombre";
        
        $stmt = $conn->prepare($search_sql);
        $searchTerm = "%$buscar_servicio%";
        $stmt->bind_param("ss", $searchTerm, $searchTerm);
        $stmt->execute();
        $search_result = $stmt->get_result();
        ?>
        
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-search-result me-2"></i>
                    Resultados para: "<?php echo htmlspecialchars($buscar_servicio); ?>"
                </h5>
                <span class="badge bg-primary"><?php echo $search_result->num_rows; ?> encontrados</span>
            </div>
            <div class="card-body">
                <?php if ($search_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th width="100">ID</th>
                                    <th>Servicio</th>
                                    <th width="150">Precio</th>
                                    <th>Descripción</th>
                                    <th width="120" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $search_result->fetch_assoc()): ?>
                                <tr>
                                    <td>SRV-<?php echo str_pad($row['id_servicio'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="bg-light rounded p-2 text-center" style="width: 40px; height: 40px;">
                                                    <i class="fas fa-concierge-bell text-primary"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($row['nombre']); ?></h6>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="fw-bold">S/. <?php echo number_format($row['precio'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($row['descripcion'] ?? 'Sin descripción'); ?></td>
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
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-4x text-muted mb-3"></i>
                        <h4>No se encontraron resultados</h4>
                        <p class="text-muted">No hay servicios que coincidan con tu búsqueda</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php $stmt->close(); ?>
    <?php endif; ?>

    <!-- Lista Completa de Servicios (cuando no hay búsqueda) -->
    <?php if (!isset($_GET['buscar_servicio'])): ?>
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Todos los Servicios</h5>
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
                                <th width="100">ID</th>
                                <th>Servicio</th>
                                <th width="150">Precio</th>
                                <th>Descripción</th>
                                <th width="120" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>SRV-<?php echo str_pad($row['id_servicio'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0 me-3">
                                            <div class="bg-light rounded p-2 text-center" style="width: 40px; height: 40px;">
                                                <i class="fas fa-concierge-bell text-primary"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($row['nombre']); ?></h6>
                                        </div>
                                    </div>
                                </td>
                                <td class="fw-bold">S/. <?php echo number_format($row['precio'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['descripcion'] ?? 'Sin descripción'); ?></td>
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
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-concierge-bell fa-4x text-muted mb-3"></i>
                    <h4>No hay servicios registrados</h4>
                    <p class="text-muted">Comienza agregando nuevos servicios haciendo clic en el botón "Nuevo Servicio"</p>
                    <button type="button" class="btn btn-success mt-3" data-bs-toggle="modal" data-bs-target="#nuevoServicioModal">
                        <i class="fas fa-plus-circle me-1"></i> Agregar Servicio
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal para Crear Nuevo Servicio -->
    <div class="modal fade" id="nuevoServicioModal" tabindex="-1" aria-labelledby="nuevoServicioModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="nuevoServicioModalLabel">
                        <i class="fas fa-plus-circle me-2"></i> Nuevo Servicio
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
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
                                <span class="input-group-text">S/.</span>
                                <input type="number" class="form-control" id="precio_servicio" name="precio" step="0.01" min="0" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descripcion_servicio" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion_servicio" name="descripcion" rows="3" placeholder="Descripción detallada del servicio (opcional)"></textarea>
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
</div>

<script>
function editarServicio(id) {
    $.ajax({
        url: 'modules/editar_servicio.php?id=' + id,
        method: 'GET',
        success: function(response) {
            $('#contenidoModalEditar').html(response);
            $('#editarServicioModal').modal('show');
        },
        error: function() {
            Swal.fire('Error', 'No se pudo cargar la información del servicio', 'error');
        }
    });
}

function eliminarServicio(id, nombre) {
    Swal.fire({
        title: '¿Eliminar servicio?',
        html: `Estás a punto de eliminar el servicio <b>${nombre}</b>. Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'modules/eliminar_servicio.php',
                method: 'POST',
                data: { id: id },
                success: function(response) {
                    if (response.success) {
                        Swal.fire(
                            'Eliminado!',
                            'El servicio ha sido eliminado.',
                            'success'
                        ).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire(
                            'Error',
                            response.message || 'No se pudo eliminar el servicio',
                            'error'
                        );
                    }
                },
                error: function() {
                    Swal.fire(
                        'Error',
                        'No se pudo completar la solicitud',
                        'error'
                    );
                }
            });
        }
    });
}

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

function descargarExcel() {
    const buscar = document.getElementById('buscar_servicio').value;
    let url = 'exportar_excel.php?tipo=servicios';
    
    if (buscar) {
        url += '&buscar=' + encodeURIComponent(buscar);
    }
    
    window.open(url, '_blank');
}

function descargarPDF() {
    const buscar = document.getElementById('buscar_servicio').value;
    let url = 'exportar_pdf.php?tipo=servicios';
    
    if (buscar) {
        url += '&buscar=' + encodeURIComponent(buscar);
    }
    
    window.open(url, '_blank');
}

$(document).ready(function() {
    // Inicializar tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Event listeners para el formulario de búsqueda
    $('#formBuscarServicio').on('submit', function(e) {
        e.preventDefault();
        realizarBusquedaServicio();
    });
    
    // Validación del formulario de nuevo servicio
    $('#formNuevoServicio').validate({
        rules: {
            nombre: {
                required: true,
                minlength: 3
            },
            precio: {
                required: true,
                number: true,
                min: 0.01
            }
        },
        messages: {
            nombre: {
                required: "Por favor ingresa el nombre del servicio",
                minlength: "El nombre debe tener al menos 3 caracteres"
            },
            precio: {
                required: "Por favor ingresa el precio del servicio",
                number: "El precio debe ser un número válido",
                min: "El precio debe ser mayor a 0"
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