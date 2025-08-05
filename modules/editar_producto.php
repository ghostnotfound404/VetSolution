<?php
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
            
            if ($update_stmt->execute()) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Producto actualizado correctamente',
                    'id_producto' => $id_producto
                ]);
            } else {
                throw new Exception('Error en la ejecución: ' . $update_stmt->error);
            }
            
            $update_stmt->close();
            
        } else {
            throw new Exception('Todos los campos obligatorios deben ser completados');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
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
    <button type="button" class="btn btn-secondary w-100 w-sm-auto mb-2 mb-sm-0 me-sm-2" data-bs-dismiss="modal">
        <i class="fas fa-times me-1"></i> Cancelar
    </button>
    <button type="button" class="btn btn-warning w-100 w-sm-auto" id="btnGuardarEdicionProducto">
        <i class="fas fa-save me-1"></i> 
        <span class="d-none d-sm-inline">Guardar Cambios</span>
        <span class="d-inline d-sm-none">Guardar</span>
    </button>
</div>

<script>
$(document).ready(function() {
    // Validación del formulario de edición
    $('#formEditarProducto').validate({
        rules: {
            nombre: {
                required: true,
                minlength: 3
            },
            precio: {
                required: true,
                number: true,
                min: 0.01
            },
            stock: {
                required: true,
                digits: true,
                min: 0
            }
        },
        messages: {
            nombre: {
                required: "Por favor ingresa el nombre del producto",
                minlength: "El nombre debe tener al menos 3 caracteres"
            },
            precio: {
                required: "Por favor ingresa el precio del producto",
                number: "El precio debe ser un número válido",
                min: "El precio debe ser mayor a 0"
            },
            stock: {
                required: "Por favor ingresa la cantidad en stock",
                digits: "El stock debe ser un número entero",
                min: "El stock no puede ser negativo"
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
    
    // Enviar formulario de edición
    $('#btnGuardarEdicionProducto').on('click', function() {
        if ($('#formEditarProducto').valid()) {
            $.ajax({
                url: 'modules/editar_producto.php',
                method: 'POST',
                data: $('#formEditarProducto').serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#editarProductoModal').modal('hide');
                        
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
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'No se pudo actualizar el producto',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Ocurrió un error al procesar la solicitud',
                        confirmButtonColor: '#dc3545'
                    });
                }
            });
        }
    });
});
</script>
