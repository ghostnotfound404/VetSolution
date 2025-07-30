<?php
include('../includes/config.php');

// Procesar formulario de nuevo producto
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $precio = trim($_POST['precio']);
    $stock = trim($_POST['stock']);
    $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';

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
        $insert_sql = "INSERT INTO productos (nombre, precio, stock, descripcion) 
                       VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("sdis", $nombre, $precio, $stock, $descripcion);
        
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

// Obtener productos (con búsqueda si se especifica)
if (isset($_GET['buscar_producto']) && !empty(trim($_GET['buscar_producto']))) {
    $buscar_producto = trim($_GET['buscar_producto']);
    
    // Usar prepared statement para seguridad
    $search_sql = "SELECT * FROM productos 
                  WHERE nombre LIKE ? 
                     OR descripcion LIKE ?
                  ORDER BY nombre";
    
    $stmt = $conn->prepare($search_sql);
    $searchTerm = "%$buscar_producto%";
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    // Obtener todos los productos
    $sql = "SELECT * FROM productos ORDER BY nombre";
    $result = $conn->query($sql);
}
?>

<div class="container productos">
    <h2>Gestionar Productos</h2>

    <!-- Sección para Buscar Producto y Crear Nuevo -->
    <div class="mb-4 search-section">
        <div class="row align-items-end">
            <div class="col-md-8">
                <h4>Buscar Producto</h4>
                <form method="GET" class="d-flex gap-2" id="formBuscarProducto">
                    <div class="flex-grow-1">
                        <input type="text" class="form-control" id="buscar_producto" name="buscar_producto" 
                               placeholder="Buscar por nombre o descripción..." 
                               value="<?php echo isset($_GET['buscar_producto']) ? htmlspecialchars($_GET['buscar_producto']) : ''; ?>">
                    </div>
                    <button type="submit" class="btn btn-secondary">Buscar</button>
                    <?php if (isset($_GET['buscar_producto'])): ?>
                        <button type="button" class="btn btn-outline-secondary" onclick="limpiarBusquedaProducto()">Limpiar</button>
                    <?php endif; ?>
                </form>
            </div>
            <div class="col-md-4 text-end">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#nuevoProductoModal">
                    <i class="fas fa-plus"></i> Crear Nuevo Producto
                </button>
            </div>
        </div>
    </div>

    <!-- Sección de Auditoría -->
    <div class="mb-4">
        <h4>Auditoría</h4>
        <div class="row">
            <div class="col-md-6">
                <select class="form-select" id="auditoria_select">
                    <option value="">Seleccionar tipo de auditoría...</option>
                    <option value="productos_general">Productos - Reporte General</option>
                    <option value="productos_stock_bajo">Productos con Stock Bajo</option>
                    <option value="productos_mas_vendidos">Productos Más Vendidos</option>
                    <option value="productos_inventario">Inventario Completo</option>
                </select>
            </div>
            <div class="col-md-6">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-success" onclick="descargarExcel()">
                        <i class="fas fa-file-excel"></i> Descargar Excel
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="descargarPDF()">
                        <i class="fas fa-file-pdf"></i> Descargar PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de productos -->
    <div class="mb-4">
        <h4>Todos los Productos</h4>
        <?php if ($result->num_rows > 0): ?>
            <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>Nombre</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Descripción</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>PROD-<?php echo $row['id_producto']; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['nombre']); ?></strong></td>
                            <td>S/. <?php echo number_format($row['precio'], 2); ?></td>
                            <td>
                                <span class="badge <?php echo $row['stock'] > 10 ? 'bg-success' : ($row['stock'] > 0 ? 'bg-warning' : 'bg-danger'); ?>">
                                    <?php echo $row['stock']; ?> unidades
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($row['descripcion'] ?? 'Sin descripción'); ?></td>
                            <td>
                                <button class="btn btn-warning btn-sm" onclick="editarProducto(<?php echo $row['id_producto']; ?>)">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="eliminarProducto(<?php echo $row['id_producto']; ?>, '<?php echo htmlspecialchars($row['nombre']); ?>')">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
        <?php else: ?>
            <div class="alert alert-info">No hay productos registrados.</div>
        <?php endif; ?>
    </div>

    <!-- Modal para Crear Nuevo Producto -->
    <div class="modal fade" id="nuevoProductoModal" tabindex="-1" aria-labelledby="nuevoProductoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="nuevoProductoModalLabel">Crear Nuevo Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="modules/productos.php" method="POST" id="formNuevoProducto">
                        <div class="mb-3">
                            <label for="nombre_producto" class="form-label">Nombre del producto <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-box"></i></span>
                                <input type="text" class="form-control" id="nombre_producto" name="nombre" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="precio" class="form-label">Precio (S/.) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                                    <input type="number" class="form-control" id="precio" name="precio" step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="stock" class="form-label">Stock inicial <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-warehouse"></i></span>
                                    <input type="number" class="form-control" id="stock" name="stock" min="0" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3" placeholder="Descripción opcional del producto"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="formNuevoProducto" class="btn btn-success">Guardar Producto</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function editarProducto(id) {
    alert('Función de editar producto ID: ' + id + ' (por implementar)');
}

function eliminarProducto(id, nombre) {
    if (confirm('¿Está seguro de que desea eliminar el producto "' + nombre + '"?')) {
        // TODO: Implementar eliminación
        alert('Funcionalidad de eliminación por implementar');
    }
}

// Función para realizar búsqueda de productos
function realizarBusquedaProducto() {
    const formData = new FormData(document.getElementById('formBuscarProducto'));
    const searchParams = new URLSearchParams(formData);
    
    $.ajax({
        url: 'modules/productos.php?' + searchParams.toString(),
        method: 'GET',
        success: function(response) {
            $('#contenido').html(response);
        },
        error: function() {
            console.error('Error al realizar la búsqueda');
        }
    });
}

// Función para limpiar búsqueda de productos
function limpiarBusquedaProducto() {
    $.ajax({
        url: 'modules/productos.php',
        method: 'GET',
        success: function(response) {
            $('#contenido').html(response);
        },
        error: function() {
            console.error('Error al limpiar la búsqueda');
        }
    });
}

// Event listeners para el formulario de búsqueda
$(document).ready(function() {
    $('#formBuscarProducto').on('submit', function(e) {
        e.preventDefault();
        realizarBusquedaProducto();
    });
});

// Funciones de auditoría
function descargarExcel() {
    const tipoAuditoria = document.getElementById('auditoria_select').value;
    if (!tipoAuditoria) {
        alert('Por favor selecciona un tipo de auditoría');
        return;
    }
    // Aquí implementarías la lógica para generar y descargar el Excel
    window.open('exportar_excel.php?tipo=' + tipoAuditoria, '_blank');
}

function descargarPDF() {
    const tipoAuditoria = document.getElementById('auditoria_select').value;
    if (!tipoAuditoria) {
        alert('Por favor selecciona un tipo de auditoría');
        return;
    }
    // Aquí implementarías la lógica para generar y descargar el PDF
    window.open('exportar_pdf.php?tipo=' + tipoAuditoria, '_blank');
}
</script>

<!-- JS específico para Productos -->
<script src="assets/js/productos.js"></script>
