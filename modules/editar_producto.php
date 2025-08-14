<?php
// Mostrar todos los errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('../includes/config.php');

// Verificar que se ha recibido el ID del producto
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="alert alert-danger">ID del producto no proporcionado</div>';
    exit();
}

$id_producto = intval($_GET['id']);

// Obtener los datos del producto
$query = "SELECT * FROM productos WHERE id_producto = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_producto);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-danger">Producto no encontrado</div>';
    exit();
}

$producto = $result->fetch_assoc();
$stmt->close();

// Procesar la actualización de los datos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Configurar cabecera para respuestas JSON
    header('Content-Type: application/json');
    
    // Registrar información de depuración
    error_log("Recibida solicitud POST en editar_producto.php");
    error_log("Datos POST recibidos: " . print_r($_POST, true));
    
    try {
        // Verificar si se recibió el ID del producto del formulario
        if (!isset($_POST['id_producto']) || empty($_POST['id_producto'])) {
            throw new Exception('ID del producto no proporcionado en el formulario');
        }
        
        $id_producto = intval($_POST['id_producto']);
        
        // Validar que todos los campos requeridos estén presentes
        if (isset($_POST['nombre']) && isset($_POST['precio']) && isset($_POST['stock'])) {
            
            $nombre = trim($_POST['nombre']);
            $precio = floatval($_POST['precio']);
            $stock = intval($_POST['stock']);
            
            // Validaciones
            if (empty($nombre)) {
                throw new Exception('El nombre del producto es obligatorio');
            }
            
            if ($precio <= 0) {
                throw new Exception('El precio debe ser mayor a 0');
            }
            
            if ($stock < 0) {
                throw new Exception('El stock no puede ser negativo');
            }
            
            // Actualizar los datos del producto
            $update_query = "UPDATE productos SET 
                            nombre = ?, 
                            precio = ?, 
                            stock = ? 
                            WHERE id_producto = ?";
            
            $update_stmt = $conn->prepare($update_query);
            
            if ($update_stmt === false) {
                throw new Exception('Error en la preparación de la consulta: ' . $conn->error);
            }
            
            $update_stmt->bind_param("sdii", $nombre, $precio, $stock, $id_producto);
            
            // Ejecutar la consulta
            $resultado = $update_stmt->execute();
            
            // Guardar error antes de cerrar el statement
            $error_mensaje = $update_stmt->error;
            $affected_rows = $update_stmt->affected_rows;
            
            $update_stmt->close();
            
            if ($resultado) {
                error_log("Producto actualizado. ID: $id_producto, Filas afectadas: $affected_rows");
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Producto actualizado correctamente',
                    'id_producto' => $id_producto,
                    'affected_rows' => $affected_rows
                ]);
            } else {
                error_log("Error al actualizar producto: " . $error_mensaje);
                throw new Exception('Error en la ejecución: ' . $error_mensaje);
            }
            
        } else {
            throw new Exception('Todos los campos obligatorios deben ser completados');
        }
    } catch (Exception $e) {
        error_log("Excepción en editar_producto.php: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage(),
            'debug_info' => 'Revisa los logs del servidor para más detalles'
        ]);
    }
    exit();
}
?>

<div class="modal-header bg-warning text-dark">
    <h5 class="modal-title d-flex align-items-center">
        <i class="fas fa-edit me-2"></i> 
        <span class="d-none d-sm-inline">Editar Producto</span>
        <span class="d-inline d-sm-none">Editar</span>
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body p-3 p-md-4">
    <form id="formEditarProducto" method="POST">
        <input type="hidden" name="id_producto" value="<?php echo $id_producto; ?>">
        
        <!-- Información del Producto -->
        <div class="row g-3">
            <div class="col-12">
                <div class="form-group mb-3">
                    <label for="nombre_editar" class="form-label fw-semibold">
                        <i class="fas fa-box text-muted me-1"></i>
                        Nombre del Producto <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text d-none d-sm-flex"><i class="fas fa-box"></i></span>
                        <input type="text" class="form-control" id="nombre_editar" name="nombre" 
                               value="<?php echo htmlspecialchars($producto['nombre']); ?>" 
                               placeholder="Nombre del producto" required>
                    </div>
                    <div class="form-text text-muted small">
                        <i class="fas fa-info-circle me-1"></i>
                        Ingresa un nombre descriptivo para el producto
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Precio y Stock -->
        <div class="row g-3">
            <div class="col-12 col-sm-6">
                <div class="form-group mb-3">
                    <label for="precio_editar" class="form-label fw-semibold">
                        <i class="fas fa-dollar-sign text-success me-1"></i>
                        Precio (S/.) <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">S/.</span>
                        <input type="number" step="0.01" min="0" class="form-control" id="precio_editar" name="precio" 
                               value="<?php echo $producto['precio']; ?>" 
                               placeholder="0.00" required>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6">
                <div class="form-group mb-3">
                    <label for="stock_editar" class="form-label fw-semibold">
                        <i class="fas fa-warehouse text-primary me-1"></i>
                        Stock <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light d-none d-sm-flex"><i class="fas fa-cubes"></i></span>
                        <input type="number" min="0" step="1" class="form-control" id="stock_editar" name="stock" 
                               value="<?php echo $producto['stock']; ?>" 
                               placeholder="Cantidad" required>
                        <span class="input-group-text bg-light text-muted small">unid.</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Información del Stock Actual -->
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info d-flex align-items-center mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    <div class="flex-grow-1">
                        <small class="mb-0">
                            <strong>Stock actual:</strong> <?php echo $producto['stock']; ?> unidades
                            <?php if ($producto['stock'] <= 10): ?>
                                <span class="badge bg-warning text-dark ms-2">Stock bajo</span>
                            <?php elseif ($producto['stock'] == 0): ?>
                                <span class="badge bg-danger ms-2">Sin stock</span>
                            <?php else: ?>
                                <span class="badge bg-success ms-2">Stock disponible</span>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<div class="modal-footer flex-column flex-sm-row p-3">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
        <i class="fas fa-times me-1"></i> Cancelar
    </button>
    <button type="button" class="btn btn-warning" id="btnGuardarEdicionProducto">
        <i class="fas fa-save me-1"></i> 
        <span class="d-none d-sm-inline">Guardar Cambios</span>
        <span class="d-inline d-sm-none">Guardar</span>
    </button>
</div>

<script>
$(document).ready(function() {
    // Función para validar el formulario manualmente
    function validarFormulario() {
        var isValid = true;
        var nombre = $('#nombre_editar').val().trim();
        var precio = $('#precio_editar').val();
        var stock = $('#stock_editar').val();
        
        // Restablecer todos los estados de validación
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').remove();
        
        // Validar nombre
        if (!nombre || nombre.length < 3) {
            $('#nombre_editar').addClass('is-invalid');
            $('#nombre_editar').closest('.form-group').append('<div class="invalid-feedback">El nombre debe tener al menos 3 caracteres</div>');
            isValid = false;
        }
        
        // Validar precio
        if (!precio || isNaN(parseFloat(precio)) || parseFloat(precio) <= 0) {
            $('#precio_editar').addClass('is-invalid');
            $('#precio_editar').closest('.form-group').append('<div class="invalid-feedback">Ingrese un precio válido mayor a 0</div>');
            isValid = false;
        }
        
        // Validar stock
        if (!stock || isNaN(parseInt(stock)) || parseInt(stock) < 0) {
            $('#stock_editar').addClass('is-invalid');
            $('#stock_editar').closest('.form-group').append('<div class="invalid-feedback">Ingrese una cantidad válida no negativa</div>');
            isValid = false;
        }
        
        return isValid;
    }
    
    // Enviar formulario de edición
    $('#btnGuardarEdicionProducto').on('click', function() {
        console.log("Botón Guardar Edición Producto clickeado");
        
        if (validarFormulario()) {
            // Construir la URL correcta para la solicitud AJAX
            // Usamos la URL específica para editar el producto
            var id_producto = $('#formEditarProducto input[name="id_producto"]').val();
            var correctUrl = 'modules/editar_producto.php?id=' + id_producto;
            var formData = $('#formEditarProducto').serialize();
            
            console.log("Enviando datos a URL:", correctUrl);
            console.log("Datos del formulario:", formData);
            
            // Mostrar indicador de carga
            $('#btnGuardarEdicionProducto').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Guardando...');
            
            $.ajax({
                url: correctUrl,
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    // Restaurar el botón
                    $('#btnGuardarEdicionProducto').prop('disabled', false).html('<i class="fas fa-save me-1"></i> <span class="d-none d-sm-inline">Guardar Cambios</span><span class="d-inline d-sm-none">Guardar</span>');
                    
                    if (response.success) {
                        $('#editarProductoModal').modal('hide');
                        
                        // Verificar si SweetAlert está disponible
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Producto actualizado!',
                                text: response.message,
                                confirmButtonColor: '#28a745'
                            }).then(() => {
                                // Recargar la página para ver los cambios
                                location.reload();
                            });
                        } else {
                            alert("Producto actualizado correctamente");
                            location.reload();
                        }
                    } else {
                        // Verificar si SweetAlert está disponible
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'No se pudo actualizar el producto',
                                confirmButtonColor: '#dc3545'
                            });
                        } else {
                            alert("Error: " + (response.message || 'No se pudo actualizar el producto'));
                        }
                    }
                },
                error: function(xhr, status, error) {
                    // Restaurar el botón
                    $('#btnGuardarEdicionProducto').prop('disabled', false).html('<i class="fas fa-save me-1"></i> <span class="d-none d-sm-inline">Guardar Cambios</span><span class="d-inline d-sm-none">Guardar</span>');
                    
                    console.error("Error en AJAX:");
                    console.error("Status:", status);
                    console.error("Error:", error);
                    console.error("Respuesta:", xhr.responseText);
                    
                    // Intentar mostrar información más detallada
                    var errorMessage = "Error al procesar la solicitud.";
                    
                    try {
                        // Intentar analizar la respuesta como JSON
                        var jsonResponse = JSON.parse(xhr.responseText);
                        if (jsonResponse && jsonResponse.message) {
                            errorMessage = jsonResponse.message;
                        }
                    } catch (e) {
                        // Si no es JSON, verificar si tiene un mensaje de error HTML
                        if (xhr.responseText && xhr.responseText.indexOf('error') > -1) {
                            errorMessage += " Por favor revisa la consola para más detalles.";
                        }
                    }
                    
                    // Verificar si SweetAlert está disponible
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error en la solicitud',
                            text: errorMessage,
                            confirmButtonColor: '#dc3545'
                        });
                    } else {
                        alert("Error en la solicitud: " + errorMessage);
                    }
                }
            });
        }
    });
});
</script>