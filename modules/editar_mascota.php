<?php
include('../includes/config.php');

// Verificar que se ha recibido el ID de la mascota
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="alert alert-danger">ID de mascota no proporcionado</div>';
    exit();
}

$id_mascota = intval($_GET['id']);

// Obtener los datos de la mascota
$query = "SELECT m.*, c.nombre as nombre_cliente, c.apellido as apellido_cliente, c.id_cliente 
          FROM mascotas m 
          INNER JOIN clientes c ON m.id_cliente = c.id_cliente 
          WHERE m.id_mascota = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_mascota);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-danger">Mascota no encontrada</div>';
    exit();
}

$mascota = $result->fetch_assoc();
$stmt->close();

// Procesar la actualización de los datos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Configurar cabecera para respuestas JSON
    header('Content-Type: application/json');
    
    try {
        // Verificar si se recibió el ID de la mascota del formulario
        if (!isset($_POST['id_mascota']) || empty($_POST['id_mascota'])) {
            throw new Exception('ID de mascota no proporcionado en el formulario');
        }
        
        // Usar el ID de la mascota del formulario
        $id_mascota = intval($_POST['id_mascota']);
        
        // Validar que todos los campos requeridos estén presentes
        if (isset($_POST['id_cliente']) && isset($_POST['nombre']) && 
            isset($_POST['fecha_nacimiento']) && isset($_POST['especie']) && isset($_POST['genero'])) {
            
            $id_cliente = intval($_POST['id_cliente']);
            $nombre = trim($_POST['nombre']);
            $fecha_nacimiento = $_POST['fecha_nacimiento'];
            $especie = $_POST['especie'];
            $raza = isset($_POST['raza']) && !empty($_POST['raza']) ? $_POST['raza'] : 'Mestizo';
            $genero = $_POST['genero'];
            $esterilizado = isset($_POST['esterilizado']) ? $_POST['esterilizado'] : 'No';
            $estado = isset($_POST['estado']) ? $_POST['estado'] : 'Activo';
        
        // Actualizar los datos de la mascota
        $update_query = "UPDATE mascotas SET 
                        id_cliente = ?, 
                        nombre = ?, 
                        fecha_nacimiento = ?, 
                        especie = ?, 
                        raza = ?, 
                        genero = ?, 
                        esterilizado = ?,
                        estado = ?
                        WHERE id_mascota = ?";
        
        try {
            // Debug - mostrar valores que se actualizarán
            error_log("Actualizando mascota $id_mascota: Cliente=$id_cliente, Nombre=$nombre, Especie=$especie");
            
            $update_stmt = $conn->prepare($update_query);
            if ($update_stmt === false) {
                throw new Exception('Error en la preparación de la consulta: ' . $conn->error);
            }
            
            // El tipo "i" para entero, "s" para string, hay 9 parámetros en total
            if (!$update_stmt->bind_param("isssssssi", $id_cliente, $nombre, $fecha_nacimiento, 
                                      $especie, $raza, $genero, $esterilizado, $estado, $id_mascota)) {
                throw new Exception('Error al vincular parámetros: ' . $update_stmt->error);
            }
            
            if ($update_stmt->execute()) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Mascota actualizada correctamente',
                    'id_mascota' => $id_mascota
                ]);
            } else {
                throw new Exception('Error en la ejecución: ' . $update_stmt->error);
            }
            
            $update_stmt->close();
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false, 
                'message' => 'Error al actualizar la mascota: ' . $e->getMessage()
            ]);
        }
    } else {
        throw new Exception('Todos los campos obligatorios deben ser completados');
    }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>

<!-- Formulario de edición de mascota -->
<form id="formEditarMascota" method="POST">
    <input type="hidden" name="id_mascota" value="<?php echo $id_mascota; ?>">
        <!-- Datos del Propietario -->
        <div class="row mb-4">
            <div class="col-12">
                <h6 class="border-bottom pb-2 fw-semibold">
                    <i class="fas fa-user text-muted me-2"></i>Datos del Propietario
                </h6>
            </div>
            <div class="col-12">
                <div class="mb-3">
                    <label for="buscar_propietario_editar" class="form-label fw-semibold">
                        <i class="fas fa-search text-muted me-1"></i>
                        Propietario <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text d-none d-sm-flex"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" id="buscar_propietario_editar" 
                               value="<?php echo htmlspecialchars($mascota['nombre_cliente'] . ' ' . $mascota['apellido_cliente']); ?>" 
                               placeholder="Nombre, apellido o documento..." readonly>
                        <button type="button" class="btn btn-outline-secondary" id="limpiar_propietario_editar">
                            <i class="fas fa-times"></i>
                        </button>
                        <input type="hidden" id="id_cliente_editar" name="id_cliente" 
                               value="<?php echo $mascota['id_cliente']; ?>" required>
                    </div>
                    <div class="form-text text-muted small">
                        <i class="fas fa-info-circle me-1"></i>
                        Haga clic en el campo para buscar otro propietario
                    </div>
                    <div id="resultados_propietario_editar" class="mt-2"></div>
                </div>
            </div>
        </div>
        
        <!-- Datos de la Mascota -->
        <div class="row mb-4">
            <div class="col-12">
                <h6 class="border-bottom pb-2 fw-semibold">
                    <i class="fas fa-paw text-muted me-2"></i>Datos de la Mascota
                </h6>
            </div>
            <div class="col-12 col-sm-6">
                <div class="mb-3">
                    <label for="nombre_mascota_editar" class="form-label fw-semibold">
                        <i class="fas fa-tag text-muted me-1"></i>
                        Nombre <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light d-none d-sm-flex"><i class="fas fa-tag"></i></span>
                        <input type="text" class="form-control" id="nombre_mascota_editar" name="nombre" 
                               value="<?php echo htmlspecialchars($mascota['nombre']); ?>" 
                               placeholder="Nombre de la mascota" required>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6">
                <div class="mb-3">
                    <label for="fecha_nacimiento_editar" class="form-label fw-semibold">
                        <i class="fas fa-calendar text-muted me-1"></i>
                        Fecha Nacimiento <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light d-none d-sm-flex"><i class="fas fa-calendar"></i></span>
                        <input type="date" class="form-control" id="fecha_nacimiento_editar" name="fecha_nacimiento" 
                               value="<?php echo $mascota['fecha_nacimiento']; ?>" required>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="mb-3">
                    <label for="especie_editar" class="form-label fw-semibold">
                        <i class="fas fa-dna text-muted me-1"></i>
                        Especie <span class="text-danger">*</span>
                    </label>
                    <select class="form-select" id="especie_editar" name="especie" required>
                        <option value="">Seleccionar...</option>
                        <option value="Canino" <?php echo $mascota['especie'] == 'Canino' ? 'selected' : ''; ?>>Canino</option>
                        <option value="Felino" <?php echo $mascota['especie'] == 'Felino' ? 'selected' : ''; ?>>Felino</option>
                        <option value="Ave" <?php echo $mascota['especie'] == 'Ave' ? 'selected' : ''; ?>>Ave</option>
                        <option value="Roedor" <?php echo $mascota['especie'] == 'Roedor' ? 'selected' : ''; ?>>Roedor</option>
                        <option value="Otro" <?php echo $mascota['especie'] == 'Otro' ? 'selected' : ''; ?>>Otro</option>
                    </select>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="mb-3">
                    <label for="raza_editar" class="form-label fw-semibold">
                        <i class="fas fa-paw text-muted me-1"></i>
                        Raza
                    </label>
                    <select class="form-select" id="raza_editar" name="raza">
                        <option value="">Seleccionar...</option>
                        <!-- Las opciones de raza se cargarán por JavaScript -->
                    </select>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="mb-3">
                    <label for="genero_editar" class="form-label fw-semibold">
                        <i class="fas fa-venus-mars text-muted me-1"></i>
                        Género <span class="text-danger">*</span>
                    </label>
                    <select class="form-select" id="genero_editar" name="genero" required>
                        <option value="">Seleccionar...</option>
                        <option value="Macho" <?php echo $mascota['genero'] == 'Macho' ? 'selected' : ''; ?>>Macho</option>
                        <option value="Hembra" <?php echo $mascota['genero'] == 'Hembra' ? 'selected' : ''; ?>>Hembra</option>
                    </select>
                </div>
            </div>
            <div class="col-12 col-sm-6">
                <div class="mb-3">
                    <label for="esterilizado_editar" class="form-label fw-semibold">
                        <i class="fas fa-cut text-muted me-1"></i>
                        Esterilizado
                    </label>
                    <select class="form-select" id="esterilizado_editar" name="esterilizado">
                        <option value="No" <?php echo $mascota['esterilizado'] == 'No' ? 'selected' : ''; ?>>No</option>
                        <option value="Si" <?php echo $mascota['esterilizado'] == 'Si' ? 'selected' : ''; ?>>Sí</option>
                    </select>
                </div>
            </div>
            <div class="col-12 col-sm-6">
                <div class="mb-3">
                    <label for="estado_editar" class="form-label fw-semibold">
                        <i class="fas fa-heartbeat text-muted me-1"></i>
                        Estado
                    </label>
                    <select class="form-select" id="estado_editar" name="estado">
                        <option value="Activo" <?php echo $mascota['estado'] == 'Activo' ? 'selected' : ''; ?>>Activo</option>
                        <option value="Inactivo" <?php echo $mascota['estado'] == 'Inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                        <option value="Fallecido" <?php echo $mascota['estado'] == 'Fallecido' ? 'selected' : ''; ?>>Fallecido</option>
                    </select>
                </div>
            </div>
        </div>
</form>

<script>
$(document).ready(function() {
    // Cargar las razas según la especie seleccionada
    cargarRazasEdicion($('#especie_editar').val(), '<?php echo $mascota['raza']; ?>');
    
    // Actualizar las razas cuando se cambie la especie
    $('#especie_editar').on('change', function() {
        cargarRazasEdicion($(this).val());
    });
    
    // Abrir modal de búsqueda al hacer clic en el campo
    $('#buscar_propietario_editar').on('click', function() {
        // Eliminamos el atributo readonly para permitir la búsqueda
        $(this).prop('readonly', false);
        $(this).select(); // Selecciona todo el texto para facilitar la búsqueda
        
        // Mostrar una alerta para indicar que está en modo búsqueda
        $('#resultados_propietario_editar').html('<div class="alert alert-info py-2">Escriba para buscar un propietario...</div>');
    });
    
    // Buscar propietario en tiempo real cuando se escribe
    $('#buscar_propietario_editar').on('input', function() {
        const busqueda = $(this).val();
        if (busqueda.length >= 2) {
            $.ajax({
                url: 'modules/buscar_propietario.php',
                method: 'GET',
                data: { q: busqueda, modo: 'editar' },
                success: function(response) {
                    $('#resultados_propietario_editar').html(response);
                }
            });
        } else if (busqueda.length === 0) {
            $('#resultados_propietario_editar').html('<div class="alert alert-info py-2">Escriba para buscar un propietario...</div>');
        }
    });
    
    // Al perder el foco del campo de búsqueda, restaurar readonly si no se ha seleccionado un propietario
    $('#buscar_propietario_editar').on('blur', function() {
        // Damos tiempo para que se procese el click en los resultados
        setTimeout(() => {
            // Si no hay un ID de cliente seleccionado, restauramos el valor original
            if ($('#id_cliente_editar').val() === '') {
                $(this).val('');
                $('#resultados_propietario_editar').html('<div class="alert alert-warning py-2">No se ha seleccionado un propietario</div>');
            } else {
                // Si hay un ID seleccionado, volvemos a poner el campo como readonly
                $(this).prop('readonly', true);
            }
        }, 200);
    });
    
    // Limpiar campo de búsqueda de propietario
    $('#limpiar_propietario_editar').on('click', function() {
        // Limpiar el campo de búsqueda y quitar readonly para permitir escribir
        $('#buscar_propietario_editar')
            .val('')
            .prop('readonly', false)
            .focus();
        
        // Limpiar el ID del cliente
        $('#id_cliente_editar').val('');
        
        // Mostrar mensaje informativo
        $('#resultados_propietario_editar').html('<div class="alert alert-warning py-2">Por favor, busque y seleccione un nuevo propietario</div>');
    });
    
    // Manejar envío del formulario
    $('#formEditarMascota').on('submit', function(e) {
        e.preventDefault();
        
        // Mostrar loading en el botón
        const $submitBtn = $('button[type="submit"]');
        const originalText = $submitBtn.html();
        $submitBtn.html('<i class="fas fa-spinner fa-spin me-1"></i> Guardando...').prop('disabled', true);
        
        $.ajax({
            url: 'modules/editar_mascota.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            timeout: 10000,
            success: function(response) {
                if (response.success) {
                    // Mostrar mensaje de éxito con SweetAlert
                    Swal.fire({
                        title: '¡Actualizado!',
                        html: `
                            <div class="text-center">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <p class="mb-0">La mascota ha sido actualizada correctamente.</p>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonColor: '#28a745',
                        confirmButtonText: '<i class="fas fa-check me-1"></i> Entendido',
                        customClass: {
                            popup: 'swal-responsive'
                        }
                    }).then(() => {
                        // Cerrar modal y recargar la página
                        $('#editarMascotaModal').modal('hide');
                        location.reload();
                    });
                } else {
                    // Mostrar error
                    Swal.fire({
                        title: 'Error al actualizar',
                        html: `
                            <div class="text-center">
                                <i class="fas fa-exclamation-circle fa-3x text-danger mb-3"></i>
                                <p class="mb-0">${response.message || 'No se pudo actualizar la mascota'}</p>
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
                let errorMessage = 'Error al procesar la solicitud';
                
                if (status === 'timeout') {
                    errorMessage = 'La solicitud tardó demasiado tiempo. Verifica tu conexión.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Error interno del servidor.';
                } else if (xhr.status === 404) {
                    errorMessage = 'Recurso no encontrado.';
                }
                
                Swal.fire({
                    title: 'Error de conexión',
                    html: `
                        <div class="text-center">
                            <i class="fas fa-wifi fa-3x text-danger mb-3"></i>
                            <p class="mb-0">${errorMessage}</p>
                        </div>
                    `,
                    icon: 'error',
                    confirmButtonColor: '#dc3545',
                    confirmButtonText: '<i class="fas fa-refresh me-1"></i> Reintentar',
                    customClass: {
                        popup: 'swal-responsive'
                    }
                });
            },
            complete: function() {
                // Restaurar botón
                $submitBtn.html(originalText).prop('disabled', false);
            }
        });
    });
    
    // Ajustar tamaño de textarea en dispositivos móviles
    if (window.innerWidth < 768) {
        $('.form-control, .form-select').css('font-size', '16px');
    }
});

function cargarRazasEdicion(especie, razaSeleccionada = '') {
    const razas = {
        'Canino': ['Labrador', 'Golden Retriever', 'Pastor Alemán', 'Bulldog', 'Beagle', 'Poodle', 'Rottweiler', 'Yorkshire', 'Chihuahua', 'Mestizo'],
        'Felino': ['Persa', 'Siamés', 'Maine Coon', 'Británico', 'Ragdoll', 'Bengalí', 'Abisinio', 'Mestizo']
    };
    
    const select = $('#raza_editar');
    select.html('<option value="">Seleccionar...</option>');
    
    if (razas[especie]) {
        razas[especie].forEach(function(raza) {
            const selected = (raza === razaSeleccionada) ? 'selected' : '';
            select.append('<option value="' + raza + '" ' + selected + '>' + raza + '</option>');
        });
    }
}

function seleccionarPropietarioEditar(id, nombre) {
    // Establecer el ID del cliente seleccionado en el campo oculto
    $('#id_cliente_editar').val(id);
    
    // Actualizar el campo visible con el nombre del propietario
    $('#buscar_propietario_editar')
        .val(nombre)
        .prop('readonly', true); // Volver a hacer el campo de solo lectura
    
    // Mostrar mensaje de confirmación
    $('#resultados_propietario_editar').html('<div class="alert alert-success py-2">Propietario seleccionado: <strong>' + nombre + '</strong></div>');
    
    // Enfocar el siguiente campo para una mejor experiencia de usuario
    setTimeout(() => {
        $('#nombre_mascota_editar').focus();
    }, 300);
}
</script>
