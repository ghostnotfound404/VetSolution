<?php
include('../includes/config.php');

// Si es una petición GET, mostrar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id_cliente = intval($_GET['id']);
    
    // Obtener datos del cliente
    $sql = "SELECT * FROM clientes WHERE id_cliente = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_cliente);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Cliente no encontrado']);
        exit();
    }
    
    $cliente = $result->fetch_assoc();
    $stmt->close();
?>

<div class="modal-header bg-warning text-white">
    <h5 class="modal-title d-flex align-items-center">
        <i class="fas fa-edit me-2"></i> 
        <span class="d-none d-sm-inline">Editar Cliente</span>
        <span class="d-inline d-sm-none">Editar</span>
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body p-3 p-md-4">
    <form id="formEditarCliente">
        <input type="hidden" id="edit_id_cliente" value="<?php echo $cliente['id_cliente']; ?>">
        
        <!-- Información Personal -->
        <div class="row mb-4">
            <div class="col-12">
                <h6 class="border-bottom pb-2 fw-semibold">
                    <i class="fas fa-user text-muted me-2"></i>Información Personal
                </h6>
            </div>
            <div class="col-12 col-sm-6">
                <div class="mb-3">
                    <label for="edit_nombre" class="form-label fw-semibold">
                        <i class="fas fa-user text-muted me-1"></i>
                        Nombre <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light d-none d-sm-flex"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="edit_nombre" name="nombre" 
                               value="<?php echo htmlspecialchars($cliente['nombre']); ?>" 
                               placeholder="Nombre del cliente" required>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6">
                <div class="mb-3">
                    <label for="edit_apellido" class="form-label fw-semibold">
                        <i class="fas fa-user text-muted me-1"></i>
                        Apellido <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light d-none d-sm-flex"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="edit_apellido" name="apellido" 
                               value="<?php echo htmlspecialchars($cliente['apellido']); ?>" 
                               placeholder="Apellido del cliente" required>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Información de Contacto -->
        <div class="row mb-4">
            <div class="col-12">
                <h6 class="border-bottom pb-2 fw-semibold">
                    <i class="fas fa-phone text-muted me-2"></i>Información de Contacto
                </h6>
            </div>
            <div class="col-12 col-sm-6">
                <div class="mb-3">
                    <label for="edit_celular" class="form-label fw-semibold">
                        <i class="fas fa-phone text-muted me-1"></i>
                        Celular <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light d-none d-sm-flex"><i class="fas fa-phone"></i></span>
                        <input type="tel" class="form-control" id="edit_celular" name="celular" 
                               value="<?php echo htmlspecialchars($cliente['celular']); ?>"
                               pattern="[0-9]{9}" maxlength="9" placeholder="987654321" required>
                    </div>
                    <div class="form-text text-muted small">
                        <i class="fas fa-info-circle me-1"></i>
                        9 dígitos (sin espacios ni guiones)
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6">
                <div class="mb-3">
                    <label for="edit_dni" class="form-label fw-semibold">
                        <i class="fas fa-id-card text-muted me-1"></i>
                        DNI
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light d-none d-sm-flex"><i class="fas fa-id-card"></i></span>
                        <input type="text" class="form-control" id="edit_dni" name="dni" 
                               value="<?php echo htmlspecialchars($cliente['dni']); ?>"
                               pattern="[0-9]{8}" maxlength="8" placeholder="12345678">
                    </div>
                    <div class="form-text text-muted small">
                        <i class="fas fa-info-circle me-1"></i>
                        8 dígitos (opcional)
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Dirección -->
        <div class="row mb-4">
            <div class="col-12">
                <h6 class="border-bottom pb-2 fw-semibold">
                    <i class="fas fa-map-marker-alt text-muted me-2"></i>Dirección
                </h6>
            </div>
            <div class="col-12">
                <div class="mb-3">
                    <label for="edit_direccion" class="form-label fw-semibold">
                        <i class="fas fa-map-marker-alt text-muted me-1"></i>
                        Dirección completa
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light d-none d-sm-flex"><i class="fas fa-map-marker-alt"></i></span>
                        <textarea class="form-control" id="edit_direccion" name="direccion" rows="2" 
                                  placeholder="Ej. Av. Los Pinos 123, San Isidro, Lima"><?php echo htmlspecialchars($cliente['direccion']); ?></textarea>
                    </div>
                    <div class="form-text text-muted small">
                        <i class="fas fa-info-circle me-1"></i>
                        Dirección completa incluyendo distrito (opcional)
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Información adicional -->
        <div class="row">
            <div class="col-12">
                <div class="alert alert-light border d-flex align-items-center mb-0">
                    <i class="fas fa-lightbulb text-warning me-2"></i>
                    <div class="flex-grow-1">
                        <small class="mb-0 text-muted">
                            <strong>Tip:</strong> Asegúrate de que la información de contacto esté actualizada para poder comunicarte con el cliente.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<div class="modal-footer flex-column flex-sm-row p-3">
    <button type="button" class="btn btn-secondary w-100 w-sm-auto mb-2 mb-sm-0 me-sm-2" data-bs-dismiss="modal">
        <i class="fas fa-times me-1"></i> Cancelar
    </button>
    <button type="button" class="btn btn-warning w-100 w-sm-auto" onclick="confirmarEdicion()">
        <i class="fas fa-save me-1"></i> 
        <span class="d-none d-sm-inline">Guardar Cambios</span>
        <span class="d-inline d-sm-none">Guardar</span>
    </button>
</div>

<script>
function confirmarEdicion() {
    // Validar campos obligatorios
    const nombre = $('#edit_nombre').val().trim();
    const apellido = $('#edit_apellido').val().trim();
    const celular = $('#edit_celular').val().trim();
    
    if (!nombre || !apellido || !celular) {
        Swal.fire({
            icon: 'warning',
            title: 'Campos obligatorios',
            text: 'Por favor complete todos los campos obligatorios',
            customClass: {
                popup: 'swal-responsive'
            }
        });
        return;
    }
    
    // Validar formato de celular
    if (!/^[0-9]{9}$/.test(celular)) {
        Swal.fire({
            icon: 'warning',
            title: 'Celular inválido',
            text: 'El celular debe tener exactamente 9 dígitos',
            customClass: {
                popup: 'swal-responsive'
            }
        });
        return;
    }
    
    // Validar DNI si se proporciona
    const dni = $('#edit_dni').val().trim();
    if (dni && !/^[0-9]{8}$/.test(dni)) {
        Swal.fire({
            icon: 'warning',
            title: 'DNI inválido',
            text: 'El DNI debe tener exactamente 8 dígitos',
            customClass: {
                popup: 'swal-responsive'
            }
        });
        return;
    }
    
    // Mostrar loading en el botón
    const $submitBtn = $('button[onclick="confirmarEdicion()"]');
    const originalText = $submitBtn.html();
    $submitBtn.html('<i class="fas fa-spinner fa-spin me-1"></i> Guardando...').prop('disabled', true);
    
    // Preparar datos
    const datos = {
        id: $('#edit_id_cliente').val(),
        nombre: nombre,
        apellido: apellido,
        celular: celular,
        dni: dni,
        direccion: $('#edit_direccion').val().trim()
    };
    
    $.ajax({
        url: 'modules/editar_cliente.php',
        method: 'POST',
        data: datos,
        dataType: 'json',
        timeout: 10000,
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    title: '¡Actualizado!',
                    html: `
                        <div class="text-center">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <p class="mb-0">Los datos del cliente han sido actualizados correctamente.</p>
                        </div>
                    `,
                    icon: 'success',
                    confirmButtonColor: '#28a745',
                    confirmButtonText: '<i class="fas fa-check me-1"></i> Entendido',
                    customClass: {
                        popup: 'swal-responsive'
                    }
                }).then(() => {
                    // Cerrar modal y recargar
                    $('#editarClienteModal').modal('hide');
                    location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Error al actualizar',
                    html: `
                        <div class="text-center">
                            <i class="fas fa-exclamation-circle fa-3x text-danger mb-3"></i>
                            <p class="mb-0">${response.message || 'No se pudieron actualizar los datos'}</p>
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
}

$(document).ready(function() {
    // Ajustar tamaño de campos en dispositivos móviles
    if (window.innerWidth < 768) {
        $('.form-control, .form-select').css('font-size', '16px');
    }
    
    // Validación en tiempo real
    $('#edit_celular').on('input', function() {
        this.value = this.value.replace(/\D/g, '').slice(0, 9);
    });
    
    $('#edit_dni').on('input', function() {
        this.value = this.value.replace(/\D/g, '').slice(0, 8);
    });
    
    // Envío del formulario con Enter
    $('#formEditarCliente').on('keypress', function(e) {
        if (e.which === 13) { // Enter
            e.preventDefault();
            confirmarEdicion();
        }
    });
});
</script>

<?php
    exit();
}

// Si es una petición POST, procesar la actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_cliente = intval($_POST['id']);
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
    
    // Verificar si el DNI ya existe en otro cliente (si se proporcionó)
    if (!empty($dni)) {
        $check_dni_sql = "SELECT id_cliente FROM clientes WHERE dni = ? AND id_cliente != ?";
        $stmt_check = $conn->prepare($check_dni_sql);
        $stmt_check->bind_param("si", $dni, $id_cliente);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows > 0) {
            $errores[] = "Ya existe otro cliente registrado con este DNI";
        }
        $stmt_check->close();
    }

    if (empty($errores)) {
        try {
            $update_sql = "UPDATE clientes SET nombre = ?, apellido = ?, celular = ?, dni = ?, direccion = ? WHERE id_cliente = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("sssssi", $nombre, $apellido, $celular, $dni, $direccion, $id_cliente);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Cliente actualizado correctamente'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'No se realizaron cambios'
                    ]);
                }
            } else {
                throw new Exception('Error al ejecutar la consulta: ' . $conn->error);
            }
            
            $stmt->close();
        } catch (Exception $e) {
            echo json_encode([
                'success' => false, 
                'message' => 'Error interno del servidor: ' . $e->getMessage()
            ]);
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => implode('\n', $errores)
        ]);
    }
    
    $conn->close();
    exit();
}
?>