<?php
include('../includes/config.php');
include('../includes/pagination.php');

// Inicializar sistema de paginación
$pagination = new PaginationHelper($conn, 10);
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$filtro_fecha = isset($_GET['filtro_fecha']) ? $_GET['filtro_fecha'] : '';

// Obtener ventas con paginación
$where_conditions = [];
$params = [];

// Aplicar filtro de fecha si existe
if (!empty($filtro_fecha)) {
    switch ($filtro_fecha) {
        case 'hoy':
            $where_conditions[] = "DATE(v.fecha_venta) = CURDATE()";
            break;
        case 'semana':
            $where_conditions[] = "v.fecha_venta >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'mes':
            $where_conditions[] = "v.fecha_venta >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
    }
}

$where_clause = !empty($where_conditions) ? implode(' AND ', $where_conditions) : '';
$joins = "LEFT JOIN mascotas m ON v.id_mascota = m.id_mascota 
          LEFT JOIN clientes c ON m.id_cliente = c.id_cliente
          LEFT JOIN productos p ON v.tipo_item = 'producto' AND v.id_item = p.id_producto
          LEFT JOIN servicios s ON v.tipo_item = 'servicio' AND v.id_item = s.id_servicio";

// Verificamos si la columna tipo_negocio existe
$result = $conn->query("SHOW COLUMNS FROM ventas LIKE 'tipo_negocio'");
$column_exists = ($result->num_rows > 0);

$select = "v.id_venta, v.fecha_venta as fecha, v.subtotal, v.medio_pago,
           m.nombre as nombre_mascota,
           CONCAT(c.nombre, ' ', c.apellido) as nombre_cliente,
           CASE 
               WHEN v.tipo_item = 'producto' THEN p.nombre 
               WHEN v.tipo_item = 'servicio' THEN s.nombre 
           END as item_nombre,
           v.tipo_item, v.cantidad, v.precio_unitario";

// Solo añadimos la columna tipo_negocio al select si existe en la tabla
if ($column_exists) {
    $select .= ", IFNULL(v.tipo_negocio, 'clinica') as tipo_negocio";
} else {
    $select .= ", 'clinica' as tipo_negocio";
    
    // Mostrar mensaje de advertencia para actualizar la base de datos
    echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
            <strong>¡Atención!</strong> Es necesario actualizar la estructura de la base de datos. 
            <a href="install/update_ventas_table.php" class="alert-link">Haga clic aquí para actualizar</a>.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
};

// Si hay búsqueda, usar searchWithPagination
if (!empty($buscar)) {
    $search_fields = ['m.nombre', 'c.nombre', 'c.apellido', 'p.nombre', 's.nombre'];
    $ventasData = $pagination->searchWithPagination(
        'ventas v', 
        $search_fields, 
        $buscar, 
        $select, 
        $where_clause, 
        'v.fecha_venta DESC'
    );
} else {
    $ventasData = $pagination->getPaginatedData(
        'ventas v', 
        $select, 
        $joins, 
        $where_clause, 
        'v.fecha_venta DESC'
    );
}

$ventas = $ventasData['data'];
$paginationInfo = $ventasData['pagination'];

// Obtener ingresos por medio de pago (solo necesitamos efectivo para calcular el total en caja)
$ingresos_efectivo = $conn->query("SELECT COALESCE(SUM(subtotal), 0) as total FROM ventas WHERE medio_pago = 'Efectivo'")->fetch_assoc()['total'];

// Obtener total de egresos
$total_egresos = $conn->query("SELECT COALESCE(SUM(monto), 0) as total FROM egresos")->fetch_assoc()['total'];

// Calcular total en caja (ingresos en efectivo - egresos)
$total_caja = $ingresos_efectivo - $total_egresos;

// Verificar si hay un valor guardado para el total en caja
$sql_total_caja = "SELECT valor FROM configuracion WHERE clave = 'total_caja' LIMIT 1";
$result_total_caja = $conn->query($sql_total_caja);
$valor_guardado = 0;

if ($result_total_caja->num_rows > 0) {
    $valor_guardado = floatval($result_total_caja->fetch_assoc()['valor']);
} else {
    // Si no existe, crear el registro
    $sql = "INSERT INTO configuracion (clave, valor) VALUES ('total_caja', '0')";
    $conn->query($sql);
}

// Procesar actualización de valor
if (isset($_POST['actualizar_total_caja'])) {
    $nuevo_valor = floatval($_POST['nuevo_total_caja']);
    $sql_update = "UPDATE configuracion SET valor = '$nuevo_valor' WHERE clave = 'total_caja'";
    
    if ($conn->query($sql_update) === TRUE) {
        $valor_guardado = $nuevo_valor;
        echo '<div class="alert alert-success" role="alert">Total en caja actualizado correctamente.</div>';
    } else {
        echo '<div class="alert alert-danger" role="alert">Error al actualizar el valor: ' . $conn->error . '</div>';
    }
}
?>

<div class="container-fluid px-4">

<div class="container-fluid px-4 caja">
    <!-- Encabezado con estadísticas -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-cash-register me-2"></i>Reporte de Caja</h2>
    </div>

    <!-- Tarjeta de Total en Caja -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="card-title mb-3"><i class="fas fa-cash-register me-2 text-danger"></i>Total en Caja</h5>
                            <div class="mb-3">
                                <span class="text-muted">Valor actual:</span>
                                <h3 class="mb-0">S/ <?php echo number_format($valor_guardado, 2); ?></h3>
                                <small class="text-muted">Último valor establecido manualmente</small>
                            </div>
                            <div class="mt-3">
                                <form method="POST" class="row g-2">
                                    <div class="col-md-8">
                                        <div class="input-group">
                                            <span class="input-group-text">S/</span>
                                            <input type="number" class="form-control" name="nuevo_total_caja" step="0.01" min="0" placeholder="Nuevo valor" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-primary w-100" name="actualizar_total_caja">Actualizar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title"><i class="fas fa-calculator me-2"></i>Cálculo Automático</h6>
                                    <p class="card-text mb-2">Basado en transacciones registradas:</p>
                                    <div class="row mb-2">
                                        <div class="col-md-6">
                                            <small class="text-muted">Efectivo:</small>
                                            <div class="fw-bold">S/ <?php echo number_format($ingresos_efectivo, 2); ?></div>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted">Egresos:</small>
                                            <div class="fw-bold">S/ <?php echo number_format($total_egresos, 2); ?></div>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>Total calculado:</span>
                                        <h5 class="mb-0">S/ <?php echo number_format($total_caja, 2); ?></h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Búsqueda, Filtros y Exportación -->
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-search me-2"></i>Buscar Ventas</h5>
            <div>
                <button type="button" class="btn btn-danger me-2" id="btnCuadrarCaja">
                    <i class="fas fa-balance-scale me-2"></i> Cuadrar Caja
                </button>
                <button type="button" class="btn btn-warning" id="btnCerrarCaja">
                    <i class="fas fa-door-closed me-2"></i> Cerrar Caja
                </button>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" class="form-control" name="buscar" 
                               placeholder="Buscar por cliente, mascota o producto..." 
                               value="<?php echo htmlspecialchars($buscar); ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="filtro_fecha" class="form-select">
                        <option value="">Todas las fechas</option>
                        <option value="hoy" <?php echo $filtro_fecha == 'hoy' ? 'selected' : ''; ?>>Hoy</option>
                        <option value="semana" <?php echo $filtro_fecha == 'semana' ? 'selected' : ''; ?>>Última semana</option>
                        <option value="mes" <?php echo $filtro_fecha == 'mes' ? 'selected' : ''; ?>>Último mes</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-secondary w-100" onclick="limpiarFiltros()">
                        <i class="fas fa-times"></i> Limpiar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de Ventas -->
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Registro de Ventas</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                        <tr>
                            <th>Fecha</th>
                            <th>Cliente / Mascota</th>
                            <th>Tipo</th>
                            <th>Item</th>
                            <th>Cantidad</th>
                            <th>Subtotal</th>
                            <th>Medio Pago</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($ventas) > 0): ?>
                            <?php foreach ($ventas as $row): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($row['fecha'])); ?></td>
                                <td>
                                    <?php 
                                    if (!empty($row['nombre_cliente'])) {
                                        echo htmlspecialchars($row['nombre_cliente']);
                                        if (!empty($row['nombre_mascota'])) {
                                            echo '<br><small class="text-muted">Mascota: ' . htmlspecialchars($row['nombre_mascota']) . '</small>';
                                        }
                                    } else {
                                        echo '<span class="text-muted">Cliente no registrado</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $row['tipo_negocio'] == 'clinica' ? 'purple' : 
                                             ($row['tipo_negocio'] == 'farmacia' ? 'dark' : 'secondary'); 
                                    ?>">
                                        <?php echo ucfirst($row['tipo_negocio']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($row['item_nombre']); ?>
                                    <br><small class="text-muted"><?php echo ucfirst($row['tipo_item']); ?></small>
                                </td>
                                <td><?php echo $row['cantidad']; ?></td>
                                <td>S/ <?php echo number_format($row['subtotal'], 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $row['medio_pago'] == 'Efectivo' ? 'success' : 
                                            ($row['medio_pago'] == 'Tarjeta' ? 'primary' : 
                                            ($row['medio_pago'] == 'Yape' ? 'danger' : 'warning')); 
                                    ?>">
                                        <?php echo htmlspecialchars($row['medio_pago']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <i class="fas fa-cash-register fa-3x text-muted mb-3"></i>
                                    <h4>No hay ventas registradas</h4>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación -->
            <?php echo $pagination->generatePaginationHTML($paginationInfo, '#/caja'); ?>
            
        </div>
    </div>
</div>

<style>
/* Estilos personalizados para la caja */
.bg-purple {
    background-color: #9b59b6 !important;
}
.text-purple {
    color: #9b59b6 !important;
}
.modal-confirm .modal-content {
    padding: 20px;
    border-radius: 5px;
    border: none;
}
.modal-confirm .modal-header {
    border-bottom: none;
    position: relative;
    text-align: center;
    margin: -20px -20px 0;
    border-radius: 5px 5px 0 0;
    padding: 35px;
}
.modal-confirm .modal-header.bg-danger {
    background-color: #dc3545;
}
.modal-confirm h4 {
    text-align: center;
    font-size: 26px;
    margin: 30px 0 -15px;
}
.modal-confirm .form-control, .modal-confirm .btn {
    min-height: 40px;
    border-radius: 3px; 
}
.modal-confirm .close {
    position: absolute;
    top: 15px;
    right: 15px;
    color: #fff;
    text-shadow: none;
    opacity: 0.5;
}
.modal-confirm .close:hover {
    opacity: 0.8;
}
.modal-confirm .btn {
    color: #fff;
    border-radius: 4px;
    background: #82ce34;
    text-decoration: none;
    transition: all 0.4s;
    line-height: normal;
    padding: 6px 20px;
    margin: 0 5px;
    min-width: 120px;
    border: none;
}
.modal-confirm .btn-secondary {
    background: #c1c1c1;
}
.modal-confirm .btn-secondary:hover, .modal-confirm .btn-secondary:focus {
    background: #a8a8a8;
}
.modal-confirm .btn-danger {
    background: #dc3545;
}
.modal-confirm .btn-danger:hover, .modal-confirm .btn-danger:focus {
    background: #bb2d3b;
}
</style>

<!-- Modal para generar el reporte de caja -->
<div class="modal fade" id="modalCuadrarCaja" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-invoice me-2"></i>Generar Reporte de Caja</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Seleccione las opciones para generar el reporte de caja:</p>
                <form id="formCuadrarCaja">
                    <div class="mb-3">
                        <label class="form-label">Formato de exportación</label>
                        <select class="form-select" id="formatoExportacion">
                            <option value="excel">Excel</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Incluir en el reporte</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="incluirDetalles" checked>
                            <label class="form-check-label" for="incluirDetalles">Detalles de transacciones</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="incluirResumen" checked>
                            <label class="form-check-label" for="incluirResumen">Resumen por tipo de negocio</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="incluirMediosPago" checked>
                            <label class="form-check-label" for="incluirMediosPago">Resumen por medios de pago</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGenerarReporte">Generar Reporte</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para cerrar caja -->
<div class="modal fade modal-confirm" id="modalCerrarCaja" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <div class="icon-box">
                    <i class="fas fa-exclamation-triangle fa-3x text-white"></i>
                </div>
            </div>
            <div class="modal-body text-center">
                <h4>¿Estás seguro?</h4>
                <p class="text-danger">¡Atención! Estás a punto de eliminar todas las transacciones del apartado de caja.</p>
                <p class="alert alert-warning">
                    <i class="fas fa-info-circle me-2"></i> Recuerda descargar tu cierre de caja ya que no se podrá recuperar.
                </p>
                <div class="mt-4">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmarCerrarCaja">Sí, Cerrar Caja</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function limpiarFiltros() {
    window.location.href = 'index.php#/caja';
}

// Inicializar los modales
document.addEventListener('DOMContentLoaded', function() {
    // Modal de Cuadrar Caja
    const modalCuadrarCaja = new bootstrap.Modal(document.getElementById('modalCuadrarCaja'));
    document.getElementById('btnCuadrarCaja').addEventListener('click', function() {
        modalCuadrarCaja.show();
    });
    
    // Modal de Cerrar Caja
    const modalCerrarCaja = new bootstrap.Modal(document.getElementById('modalCerrarCaja'));
    document.getElementById('btnCerrarCaja').addEventListener('click', function() {
        modalCerrarCaja.show();
    });
    
    // Generar reporte
    document.getElementById('btnGenerarReporte').addEventListener('click', function() {
        // Construir URL con parámetros de exportación
        const formato = document.getElementById('formatoExportacion').value;
        const incluirDetalles = document.getElementById('incluirDetalles').checked;
        const incluirResumen = document.getElementById('incluirResumen').checked;
        const incluirMediosPago = document.getElementById('incluirMediosPago').checked;
        
        const url = `modules/exportar_caja.php?formato=${formato}&detalles=${incluirDetalles ? '1' : '0'}&resumen=${incluirResumen ? '1' : '0'}&medios_pago=${incluirMediosPago ? '1' : '0'}`;
        
        // Abrir en nueva pestaña
        window.open(url, '_blank');
        
        // Cerrar modal
        modalCuadrarCaja.hide();
    });
    
    // Confirmar cierre de caja
    document.getElementById('btnConfirmarCerrarCaja').addEventListener('click', function() {
        // Hacer petición AJAX para cerrar la caja
        fetch('modules/cerrar_caja.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            }
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                // Recargar la página
                window.location.reload();
            } else {
                alert('Error al cerrar la caja: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cerrar la caja. Por favor, inténtelo de nuevo.');
        });
    });
});
</script>