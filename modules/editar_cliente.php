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
    <h5 class="modal-title">
        <i class="fas fa-edit me-2"></i> Editar Cliente
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <form id="formEditarCliente">
        <input type="hidden" id="edit_id_cliente" value="<?php echo $cliente['id_cliente']; ?>">
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="edit_nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control" id="edit_nombre" name="nombre" 
                           value="<?php echo htmlspecialchars($cliente['nombre']); ?>" required>
                </div>
            </div>
            <div class="col-md-6">
                <label for="edit_apellido" class="form-label">Apellido <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control" id="edit_apellido" name="apellido" 
                           value="<?php echo htmlspecialchars($cliente['apellido']); ?>" required>
                </div>
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="edit_celular" class="form-label">Celular <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                    <input type="tel" class="form-control" id="edit_celular" name="celular" 
                           value="<?php echo htmlspecialchars($cliente['celular']); ?>"
                           pattern="[0-9]{9}" maxlength="9" required>
                </div>
                <small class="text-muted">9 dígitos (sin espacios ni guiones)</small>
            </div>
            <div class="col-md-6">
                <label for="edit_dni" class="form-label">DNI</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                    <input type="text" class="form-control" id="edit_dni" name="dni" 
                           value="<?php echo htmlspecialchars($cliente['dni']); ?>"
                           pattern="[0-9]{8}" maxlength="8">
                </div>
                <small class="text-muted">8 dígitos (opcional)</small>
            </div>
        </div>
        
        <div class="mb-3">
            <label for="edit_direccion" class="form-label">Dirección</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                <textarea class="form-control" id="edit_direccion" name="direccion" rows="2"><?php echo htmlspecialchars($cliente['direccion']); ?></textarea>
            </div>
        </div>
    </form>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
        <i class="fas fa-times me-1"></i> Cancelar
    </button>
    <button type="button" class="btn btn-warning" onclick="confirmarEdicion()">
        <i class="fas fa-save me-1"></i> Guardar Cambios
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
            text: 'Por favor complete todos los campos obligatorios'
        });
        return;
    }
    
    // Validar formato de celular
    if (!/^[0-9]{9}$/.test(celular)) {
        Swal.fire({
            icon: 'warning',
            title: 'Celular inválido',
            text: 'El celular debe tener exactamente 9 dígitos'
        });
        return;
    }
    
    // Validar DNI si se proporciona
    const dni = $('#edit_dni').val().trim();
    if (dni && !/^[0-9]{8}$/.test(dni)) {
        Swal.fire({
            icon: 'warning',
            title: 'DNI inválido',
            text: 'El DNI debe tener exactamente 8 dígitos'
        });
        return;
    }
    
    // Mostrar confirmación como en la imagen
    Swal.fire({
        title: '¿Estás seguro?',
        text: "Se guardarán los cambios realizados",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f0ad4e',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, guardar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            actualizarCliente();
        }
    });
}

function actualizarCliente() {
    const formData = {
        id: $('#edit_id_cliente').val(),
        nombre: $('#edit_nombre').val().trim(),
        apellido: $('#edit_apellido').val().trim(),
        celular: $('#edit_celular').val().trim(),
        dni: $('#edit_dni').val().trim(),
        direccion: $('#edit_direccion').val().trim()
    };
    
    $.ajax({
        url: 'modules/editar_cliente.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Mostrar éxito como en la imagen
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: response.message,
                    confirmButtonColor: '#7c4dff',
                    confirmButtonText: 'Aceptar'
                }).then(() => {
                    // Cerrar modal
                    $('#editarClienteModal').modal('hide');
                    
                    // Recargar lista de clientes
                    $.ajax({
                        url: 'modules/clientes.php',
                        method: 'GET',
                        success: function(data) {
                            $('#contenido').html(data);
                        }
                    });
                });
            } else {
                // Mostrar error como en la imagen
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message,
                    confirmButtonColor: '#7c4dff',
                    confirmButtonText: 'OK'
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error al actualizar el cliente',
                confirmButtonColor: '#7c4dff',
                confirmButtonText: 'OK'
            });
        }
    });
}

// Validación en tiempo real
$(document).ready(function() {
    // Validación para celular
    $('#edit_celular').on('input', function() {
        var valor = $(this).val().replace(/\D/g, '');
        $(this).val(valor.substring(0, 9));
    });
    
    // Validación para DNI
    $('#edit_dni').on('input', function() {
        var valor = $(this).val().replace(/\D/g, '');
        $(this).val(valor.substring(0, 8));
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
