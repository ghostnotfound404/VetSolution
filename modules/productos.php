<?php
include('../includes/config.php');

// Procesar formulario de nuevo producto
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $precio = trim($_POST['precio']);
    $stock = trim($_POST['stock']);

    // Validaciones
    $errores = [];
    
    if (empty($nombre)) {
        $errores[] = "El nombre del producto es obligatorio";
    }
    
    if (empty($precio) || $precio <= 0) {
        $errores[] = "El precio debe ser mayor a 0";
    }
    
    if (!is_numeric($stock) || $stock < 0) {
        $errores[] = "El stock debe ser un número válido mayor o igual a 0";
    }

    if (empty($errores)) {
        $insert_sql = "INSERT INTO productos (nombre, precio, stock) 
                       VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("sdi", $nombre, $precio, $stock);
        
        if ($stmt->execute()) {
            echo "<script>
                if (window.parent && window.parent.location.hash) {
                    window.parent.location.reload();
                } else {
                    window.location.href = '../index.php#/productos';
                }
            </script>";
        } else {
            echo "<script>alert('Error al guardar producto: " . $conn->error . "');</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('Errores encontrados:\\n" . implode("\\n", $errores) . "');</script>";
    }
}

// Obtener productos (con búsqueda y/o filtro si se especifican)
$where_clauses = [];
$params = [];
$types = "";

// Procesar búsqueda por nombre
if (isset($_GET['buscar_producto']) && !empty(trim($_GET['buscar_producto']))) {
    $buscar_producto = trim($_GET['buscar_producto']);
    $where_clauses[] = "nombre LIKE ?";
    $params[] = "%$buscar_producto%";
    $types .= "s";
}

// Procesar filtros de stock
if (isset($_GET['filtro'])) {
    switch ($_GET['filtro']) {
        case 'stock_alto':
            $where_clauses[] = "stock > 10";
            break;
        case 'stock_bajo':
            $where_clauses[] = "stock > 0 AND stock <= 10";
            break;
        case 'sin_stock':
            $where_clauses[] = "stock = 0";
            break;
        // El filtro 'todos' no agrega restricciones
    }
}

// Construir la consulta SQL con las condiciones aplicadas
$sql = "SELECT * FROM productos";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY id_producto DESC";

// Ejecutar la consulta
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    // Enlazar parámetros si hay
    if (!empty($types)) {
        // Corregir la forma de enlazar parámetros
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $result = $conn->query($sql);
}
?>

<div class="container-fluid px-4 productos">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-boxes me-2"></i>Gestión de Productos</h2>
    </div>

    <!-- Tarjeta de Resumen -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Total Productos</h6>
                            <h4 class="mb-0"><?php echo $result->num_rows; ?></h4>
                        </div>
                        <i class="fas fa-box fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Stock Alto</h6>
                            <h4 class="mb-0">
                                <?php 
                                    $sql_stock_alto = "SELECT COUNT(*) as total FROM productos WHERE stock > 10";
                                    $res_stock_alto = $conn->query($sql_stock_alto);
                                    echo $res_stock_alto->fetch_assoc()['total'];
                                ?>
                            </h4>
                        </div>
                        <i class="fas fa-arrow-up fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Stock Bajo</h6>
                            <h4 class="mb-0">
                                <?php 
                                    $sql_stock_bajo = "SELECT COUNT(*) as total FROM productos WHERE stock > 0 AND stock <= 10";
                                    $res_stock_bajo = $conn->query($sql_stock_bajo);
                                    echo $res_stock_bajo->fetch_assoc()['total'];
                                ?>
                            </h4>
                        </div>
                        <i class="fas fa-arrow-down fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-danger text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Sin Stock</h6>
                            <h4 class="mb-0">
                                <?php 
                                    $sql_sin_stock = "SELECT COUNT(*) as total FROM productos WHERE stock = 0";
                                    $res_sin_stock = $conn->query($sql_sin_stock);
                                    echo $res_sin_stock->fetch_assoc()['total'];
                                ?>
                            </h4>
                        </div>
                        <i class="fas fa-times-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjeta de Búsqueda y Filtros -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-search me-2"></i>Buscar Productos</h5>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-sliders-h"></i> 
                        <?php
                        $filtro_actual = isset($_GET['filtro']) ? $_GET['filtro'] : 'todos';
                        switch ($filtro_actual) {
                            case 'stock_alto':
                                echo 'Stock alto (>10)';
                                break;
                            case 'stock_bajo':
                                echo 'Stock bajo (1-10)';
                                break;
                            case 'sin_stock':
                                echo 'Sin stock';
                                break;
                            default:
                                echo 'Todos los productos';
                        }
                        ?>
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                        <li><a class="dropdown-item <?php echo ($filtro_actual == 'todos' || !isset($_GET['filtro'])) ? 'active' : ''; ?>" href="#" onclick="filtrarProductos('todos')">Todos los productos</a></li>
                        <li><a class="dropdown-item <?php echo $filtro_actual == 'stock_alto' ? 'active' : ''; ?>" href="#" onclick="filtrarProductos('stock_alto')">Stock alto (>10)</a></li>
                        <li><a class="dropdown-item <?php echo $filtro_actual == 'stock_bajo' ? 'active' : ''; ?>" href="#" onclick="filtrarProductos('stock_bajo')">Stock bajo (1-10)</a></li>
                        <li><a class="dropdown-item <?php echo $filtro_actual == 'sin_stock' ? 'active' : ''; ?>" href="#" onclick="filtrarProductos('sin_stock')">Sin stock</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" id="formBuscarProducto" class="row g-3 align-items-center">
                <!-- Mantener el filtro actual cuando se realiza una búsqueda -->
                <?php if (isset($_GET['filtro'])): ?>
                <input type="hidden" name="filtro" value="<?php echo htmlspecialchars($_GET['filtro']); ?>">
                <?php endif; ?>
                <div class="col-md-8">
                    <div class="input-group">
                        <input type="text" class="form-control" id="buscar_producto" name="buscar_producto" 
                               placeholder="Buscar por nombre..." 
                               value="<?php echo isset($_GET['buscar_producto']) ? htmlspecialchars($_GET['buscar_producto']) : ''; ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Buscar
                        </button>
                        <?php if (isset($_GET['buscar_producto'])): ?>
                        <button type="button" class="btn btn-outline-secondary" onclick="limpiarBusquedaProducto()">
                            <i class="fas fa-times me-1"></i> Limpiar
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4 d-flex align-items-stretch">
                    <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#nuevoProductoModal">
                        <i class="fas fa-plus-circle me-1"></i> Nuevo Producto
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tarjeta de Lista de Productos -->
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Lista de Productos</h5>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-success" onclick="descargarExcel('productos')">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <?php if ($result->num_rows > 0): ?>
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="25%">Producto</th>
                                <th width="15%" class="text-center">Precio</th>
                                <th width="25%" class="text-center">Stock</th>
                                <th width="20%" class="text-center">Estado</th>
                                <th width="15%" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0 me-2">
                                            <div class="bg-light rounded p-1 text-center" style="width: 30px; height: 30px;">
                                                <i class="fas fa-box text-muted" style="font-size: 12px;"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($row['nombre']); ?></h6>
                                        </div>
                                    </div>
                                </td>
                                <td class="fw-bold text-center">S/. <?php echo number_format($row['precio'], 2); ?></td>
                                <td class="text-center">
                                    <div class="progress mx-auto" style="height: 20px; max-width: 150px;">
                                        <?php 
                                            $porcentaje = min(100, ($row['stock'] / 50) * 100); // Asumiendo 50 como stock máximo para la barra
                                            $clase = $row['stock'] > 10 ? 'bg-success' : ($row['stock'] > 0 ? 'bg-warning' : 'bg-danger');
                                        ?>
                                        <div class="progress-bar <?php echo $clase; ?>" role="progressbar" style="width: <?php echo $porcentaje; ?>%" 
                                             aria-valuenow="<?php echo $row['stock']; ?>" aria-valuemin="0" aria-valuemax="50">
                                            <?php echo $row['stock']; ?> unidades
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo $clase; ?> rounded-pill">
                                        <?php 
                                            if ($row['stock'] > 10) echo 'Disponible';
                                            elseif ($row['stock'] > 0) echo 'Poco Stock';
                                            else echo 'Agotado';
                                        ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-primary" onclick="editarProducto(<?php echo $row['id_producto']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="eliminarProducto(<?php echo $row['id_producto']; ?>, '<?php echo htmlspecialchars($row['nombre']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                        <h4>No hay productos registrados</h4>
                        <p class="text-muted">Comienza agregando nuevos productos haciendo clic en el botón "Nuevo Producto"</p>
                        <button type="button" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#nuevoProductoModal">
                            <i class="fas fa-plus-circle me-1"></i> Agregar Producto
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Crear Nuevo Producto -->
<div class="modal fade" id="nuevoProductoModal" tabindex="-1" aria-labelledby="nuevoProductoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="nuevoProductoModalLabel">
                    <i class="fas fa-plus-circle me-2"></i> Nuevo Producto
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="modules/productos.php" method="POST" id="formNuevoProducto">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="nombre_producto" class="form-label">Nombre del producto <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-box"></i></span>
                                    <input type="text" class="form-control" id="nombre_producto" name="nombre" placeholder="Ej. Hepatin Capsulas" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="precio" class="form-label">Precio (S/.) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">S/.</span>
                                    <input type="number" class="form-control" id="precio" name="precio" step="0.01" min="0" placeholder="0.00" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="stock" class="form-label">Stock inicial <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-warehouse"></i></span>
                                    <input type="number" class="form-control" id="stock" name="stock" min="0" placeholder="0" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancelar
                </button>
                <button type="submit" form="formNuevoProducto" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> Guardar Producto
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function eliminarProducto(id, nombre) {
    Swal.fire({
        title: '¿Eliminar producto?',
        html: `Estás a punto de eliminar el producto <b>${nombre}</b>. Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'modules/eliminar_producto.php',
                method: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire(
                            'Eliminado!',
                            'El producto ha sido eliminado.',
                            'success'
                        ).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire(
                            'Error',
                            response.message || 'No se pudo eliminar el producto',
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

function editarProducto(id) {
    $.ajax({
        url: 'modules/editar_producto.php',
        method: 'GET',
        data: { id: id },
        success: function(response) {
            $('#contenidoModalEditar').html(response);
            $('#editarProductoModal').modal('show');
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo cargar el formulario de edición',
                confirmButtonColor: '#dc3545'
            });
        }
    });
}

function descargarExcel(tabla) {
    window.open('modules/exportar_excel.php?tabla=' + tabla, '_blank');
}

// Función para filtrar productos por stock
function filtrarProductos(filtro) {
    // Obtener el parámetro de búsqueda actual (si existe)
    let urlParams = new URLSearchParams(window.location.search);
    let busqueda = urlParams.get('buscar_producto') || '';
    
    // Crear la URL base para la redirección
    let url = '?filtro=' + filtro;
    
    // Añadir el parámetro de búsqueda si existe
    if (busqueda) {
        url += '&buscar_producto=' + encodeURIComponent(busqueda);
    }
    
    console.log("Aplicando filtro: " + filtro + " - URL: " + url);
    
    // Redirigir a la URL con el filtro
    window.location.href = url + '#/productos';
}

// Función para limpiar la búsqueda
function limpiarBusquedaProducto() {
    // Mantener el filtro actual si existe
    let urlParams = new URLSearchParams(window.location.search);
    let filtro = urlParams.get('filtro') || '';
    
    if (filtro) {
        window.location.href = '?filtro=' + filtro + '#/productos';
    } else {
        window.location.href = '?#/productos';
    }
}

// Función para realizar la búsqueda manteniendo el filtro
function realizarBusquedaProducto() {
    let busqueda = $('#buscar_producto').val();
    let urlParams = new URLSearchParams(window.location.search);
    let filtro = urlParams.get('filtro') || '';
    
    let url = '?';
    
    if (busqueda) {
        url += 'buscar_producto=' + encodeURIComponent(busqueda);
        
        if (filtro) {
            url += '&filtro=' + filtro;
        }
    } else if (filtro) {
        url += 'filtro=' + filtro;
    }
    
    window.location.href = url + '#/productos';
}

$(document).ready(function() {
    // Inicializar tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Event listeners para el formulario de búsqueda
    $('#formBuscarProducto').on('submit', function(e) {
        e.preventDefault();
        realizarBusquedaProducto();
    });
    
    // Debug: Log current URL parameters
    console.log("Parámetros actuales:", new URLSearchParams(window.location.search).toString());
    
    // Validación del formulario de nuevo producto
    $('#formNuevoProducto').validate({
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
});
</script>

<!-- Modal para Editar Producto -->
<div class="modal fade" id="editarProductoModal" tabindex="-1" aria-labelledby="editarProductoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" id="contenidoModalEditar">
            <!-- El contenido será cargado dinámicamente mediante AJAX -->
        </div>
    </div>
</div>