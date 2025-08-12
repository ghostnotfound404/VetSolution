<?php
include('../includes/config.php');
include('../includes/pagination.php');

// Inicializar sistema de paginación
$paginationHelper = new PaginationHelper($conn);
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$buscar = isset($_GET['buscar_producto']) ? trim($_GET['buscar_producto']) : '';

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

// Obtener productos con paginación
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : '';
$where_conditions = [];
$params = [];

// Aplicar filtros de stock
switch ($filtro) {
    case 'stock_alto':
        $where_conditions[] = "stock > 10";
        break;
    case 'stock_bajo':
        $where_conditions[] = "stock > 0 AND stock <= 10";
        break;
    case 'sin_stock':
        $where_conditions[] = "stock = 0";
        break;
}

$where_clause = !empty($where_conditions) ? implode(' AND ', $where_conditions) : '';

// Si hay búsqueda, usar searchWithPagination
if (!empty($buscar)) {
    $search_fields = ['nombre'];
    $pagination_result = $paginationHelper->searchWithPagination(
        'productos', 
        $search_fields, 
        $buscar, 
        '*', 
        $where_clause, 
        'id_producto DESC',
        $params
    );
} else {
    $pagination_result = $paginationHelper->getPaginatedData(
        'productos', 
        '*', 
        '', 
        $where_clause, 
        'id_producto DESC',
        $params
    );
}

$productos = $pagination_result['data'];
$total_pages = $pagination_result['pagination']['total_pages'] ?? 1;
$current_page = $pagination_result['pagination']['current_page'] ?? 1;
$total_records = $pagination_result['pagination']['total_records'] ?? 0;
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
                            <h4 class="mb-0"><?php echo $total_records; ?></h4>
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
                <?php if (count($productos) > 0): ?>
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="25%" class="d-none d-sm-table-cell">Producto</th>
                                <th width="100%" class="d-table-cell d-sm-none">Información del Producto</th>
                                <th width="15%" class="text-center d-none d-md-table-cell">Precio</th>
                                <th width="25%" class="text-center d-none d-lg-table-cell">Stock</th>
                                <th width="20%" class="text-center d-none d-md-table-cell">Estado</th>
                                <th width="15%" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $row): ?>
                            <tr>
                                <!-- Vista Desktop - Producto -->
                                <td class="d-none d-sm-table-cell">
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
                                
                                <!-- Vista Mobile - Información Completa -->
                                <td class="d-table-cell d-sm-none">
                                    <div class="card border-0 bg-light">
                                        <div class="card-body p-3">
                                            <div class="d-flex align-items-start mb-2">
                                                <div class="flex-shrink-0 me-2">
                                                    <div class="bg-white rounded p-2 text-center" style="width: 35px; height: 35px;">
                                                        <i class="fas fa-box text-primary" style="font-size: 14px;"></i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($row['nombre']); ?></h6>
                                                    <div class="row g-2 text-sm">
                                                        <div class="col-6">
                                                            <span class="text-muted">Precio:</span>
                                                            <div class="fw-bold text-success">S/. <?php echo number_format($row['precio'], 2); ?></div>
                                                        </div>
                                                        <div class="col-6">
                                                            <span class="text-muted">Stock:</span>
                                                            <div class="fw-bold"><?php echo $row['stock']; ?> unid.</div>
                                                        </div>
                                                    </div>
                                                    <div class="mt-2">
                                                        <?php 
                                                            $clase_mobile = $row['stock'] > 10 ? 'bg-success' : ($row['stock'] > 0 ? 'bg-warning' : 'bg-danger');
                                                        ?>
                                                        <span class="badge <?php echo $clase_mobile; ?> rounded-pill">
                                                            <?php 
                                                                if ($row['stock'] > 10) echo 'Disponible';
                                                                elseif ($row['stock'] > 0) echo 'Poco Stock';
                                                                else echo 'Agotado';
                                                            ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="fw-bold text-center d-none d-md-table-cell">S/. <?php echo number_format($row['precio'], 2); ?></td>
                                <td class="text-center d-none d-lg-table-cell">
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
                                <td class="text-center d-none d-md-table-cell">
                                    <span class="badge <?php echo $clase; ?> rounded-pill">
                                        <?php 
                                            if ($row['stock'] > 10) echo 'Disponible';
                                            elseif ($row['stock'] > 0) echo 'Poco Stock';
                                            else echo 'Agotado';
                                        ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group d-none d-md-flex" role="group">
                                        <button class="btn btn-sm btn-outline-primary" onclick="editarProducto(<?php echo $row['id_producto']; ?>)" 
                                                data-bs-toggle="tooltip" title="Editar producto">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="eliminarProducto(<?php echo $row['id_producto']; ?>, '<?php echo htmlspecialchars($row['nombre']); ?>')"
                                                data-bs-toggle="tooltip" title="Eliminar producto">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Botones para móviles -->
                                    <div class="d-flex d-md-none flex-column gap-1">
                                        <button class="btn btn-sm btn-outline-primary w-100" onclick="editarProducto(<?php echo $row['id_producto']; ?>)">
                                            <i class="fas fa-edit me-1"></i> Editar
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger w-100" onclick="eliminarProducto(<?php echo $row['id_producto']; ?>, '<?php echo htmlspecialchars($row['nombre']); ?>')">
                                            <i class="fas fa-trash me-1"></i> Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Paginación -->
                    <?php echo $paginationHelper->generatePaginationHTML($pagination_result['pagination'], '#/productos'); ?>
                        
                <?php else: ?>
                    <div class="text-center py-5">
                        <?php if (!empty($buscar)): ?>
                            <i class="fas fa-search fa-4x text-muted mb-3"></i>
                            <h4>No se encontraron productos</h4>
                            <p class="text-muted">No hay productos que coincidan con tu búsqueda</p>
                        <?php else: ?>
                            <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                            <h4>No hay productos registrados</h4>
                            <p class="text-muted">Comienza agregando nuevos productos haciendo clic en el botón "Nuevo Producto"</p>
                            <button type="button" class="btn btn-success mt-3" data-bs-toggle="modal" data-bs-target="#nuevoProductoModal">
                                <i class="fas fa-plus-circle me-1"></i> Agregar Producto
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Crear Nuevo Producto -->
<div class="modal fade" id="nuevoProductoModal" tabindex="-1" aria-labelledby="nuevoProductoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title d-flex align-items-center" id="nuevoProductoModalLabel">
                    <i class="fas fa-plus-circle me-2"></i> 
                    <span class="d-none d-sm-inline">Nuevo Producto</span>
                    <span class="d-inline d-sm-none">Nuevo</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 p-md-4">
                <form action="modules/productos.php" method="POST" id="formNuevoProducto">
                    <!-- Información del Producto -->
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="mb-3">
                                <label for="nombre_producto" class="form-label fw-semibold">
                                    <i class="fas fa-box text-muted me-1"></i>
                                    Nombre del producto <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text d-none d-sm-flex"><i class="fas fa-box"></i></span>
                                    <input type="text" class="form-control" id="nombre_producto" name="nombre" 
                                           placeholder="Ej. Hepatin Capsulas" required>
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
                            <div class="mb-3">
                                <label for="precio" class="form-label fw-semibold">
                                    <i class="fas fa-dollar-sign text-success me-1"></i>
                                    Precio (S/.) <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">S/.</span>
                                    <input type="number" class="form-control" id="precio" name="precio" 
                                           step="0.01" min="0" placeholder="0.00" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <div class="mb-3">
                                <label for="stock" class="form-label fw-semibold">
                                    <i class="fas fa-warehouse text-primary me-1"></i>
                                    Stock inicial <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light d-none d-sm-flex"><i class="fas fa-warehouse"></i></span>
                                    <input type="number" class="form-control" id="stock" name="stock" 
                                           min="0" placeholder="0" required>
                                    <span class="input-group-text bg-light text-muted small">unid.</span>
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
                                        <strong>Tip:</strong> Asegúrate de ingresar el precio y stock correctos. 
                                        Podrás editarlos posteriormente si es necesario.
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
                <button type="submit" form="formNuevoProducto" class="btn btn-success w-100 w-sm-auto">
                    <i class="fas fa-save me-1"></i> 
                    <span class="d-none d-sm-inline">Guardar Producto</span>
                    <span class="d-inline d-sm-none">Guardar</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function eliminarProducto(id, nombre) {
    Swal.fire({
        title: '<i class="fas fa-exclamation-triangle text-warning me-2"></i>¿Eliminar producto?',
        html: `
            <div class="text-start">
                <p class="mb-2">Estás a punto de eliminar el producto:</p>
                <div class="card bg-light border-warning">
                    <div class="card-body p-3">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-box me-2 text-muted"></i>
                            <strong>${nombre}</strong>
                        </h6>
                    </div>
                </div>
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
                title: 'Eliminando producto...',
                html: '<div class="d-flex justify-content-center"><div class="spinner-border text-danger" role="status"><span class="visually-hidden">Cargando...</span></div></div>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                customClass: {
                    popup: 'swal-responsive'
                }
            });
            
            $.ajax({
                url: 'modules/eliminar_producto.php',
                method: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: '¡Eliminado!',
                            html: `
                                <div class="text-center">
                                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                    <p class="mb-0">El producto ha sido eliminado correctamente.</p>
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
                                    <p class="mb-0">${response.message || 'No se pudo eliminar el producto'}</p>
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
}

function editarProducto(id) {
    // Resetear el contenido del modal con loading
    $('#contenidoModalEditar').html(`
        <div class="modal-body text-center p-5">
            <div class="d-flex flex-column align-items-center">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <h5 class="text-muted">Cargando información del producto...</h5>
                <p class="text-muted small mb-0">Por favor, espera un momento</p>
            </div>
        </div>
    `);
    
    // Mostrar el modal inmediatamente
    $('#editarProductoModal').modal('show');
    
    $.ajax({
        url: 'modules/editar_producto.php',
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
                errorMessage = 'Producto no encontrado.';
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
                            <button type="button" class="btn btn-primary" onclick="editarProducto(${id})">
                                <i class="fas fa-refresh me-1"></i> Reintentar
                            </button>
                        </div>
                    </div>
                </div>
            `);
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
    
    .productos .card-body .table {
        margin-bottom: 0;
    }
    
    .productos .card-body .table td {
        border: none;
        padding: 0.5rem 0.25rem;
    }
    
    /* Mejorar la vista mobile de productos */
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
</script>

<!-- Modal para Editar Producto -->
<div class="modal fade" id="editarProductoModal" tabindex="-1" aria-labelledby="editarProductoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" id="contenidoModalEditar">
            <!-- El contenido será cargado dinámicamente mediante AJAX -->
            <div class="modal-body text-center p-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="mt-3 text-muted">Cargando información del producto...</p>
            </div>
        </div>
    </div>
</div>