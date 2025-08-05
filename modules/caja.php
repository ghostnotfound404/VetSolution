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

$select = "v.id_venta, v.fecha_venta as fecha, v.subtotal, v.medio_pago,
           m.nombre as nombre_mascota,
           CONCAT(c.nombre, ' ', c.apellido) as nombre_cliente,
           CASE 
               WHEN v.tipo_item = 'producto' THEN p.nombre 
               WHEN v.tipo_item = 'servicio' THEN s.nombre 
           END as item_nombre,
           v.tipo_item, v.cantidad, v.precio_unitario";

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

// Obtener estadísticas
$total_ventas = $conn->query("SELECT COUNT(*) as total FROM ventas")->fetch_assoc()['total'];
$ventas_hoy = $conn->query("SELECT COUNT(*) as total FROM ventas WHERE DATE(fecha_venta) = CURDATE()")->fetch_assoc()['total'];
$ingresos_hoy = $conn->query("SELECT COALESCE(SUM(subtotal), 0) as total FROM ventas WHERE DATE(fecha_venta) = CURDATE()")->fetch_assoc()['total'];
$total_ingresos = $conn->query("SELECT COALESCE(SUM(subtotal), 0) as total FROM ventas")->fetch_assoc()['total'];
?>

<div class="container-fluid px-4">

<div class="container-fluid px-4 caja">
    <!-- Encabezado con estadísticas -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-cash-register me-2"></i>Reporte de Caja</h2>
    </div>

    <!-- Tarjetas de Resumen -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Total Ventas</h6>
                            <h4 class="mb-0"><?php echo $total_ventas; ?></h4>
                        </div>
                        <i class="fas fa-shopping-cart fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Ventas Hoy</h6>
                            <h4 class="mb-0"><?php echo $ventas_hoy; ?></h4>
                        </div>
                        <i class="fas fa-chart-line fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-info text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Ingresos Hoy</h6>
                            <h4 class="mb-0">S/ <?php echo number_format($ingresos_hoy, 2); ?></h4>
                        </div>
                        <i class="fas fa-coins fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Total Ingresos</h6>
                            <h4 class="mb-0">S/ <?php echo number_format($total_ingresos, 2); ?></h4>
                        </div>
                        <i class="fas fa-money-bill-wave fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Búsqueda y Filtros -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="fas fa-search me-2"></i>Buscar Ventas</h5>
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
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Cliente / Mascota</th>
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
                                    <?php echo htmlspecialchars($row['item_nombre']); ?>
                                    <br><small class="text-muted"><?php echo ucfirst($row['tipo_item']); ?></small>
                                </td>
                                <td><?php echo $row['cantidad']; ?></td>
                                <td>S/ <?php echo number_format($row['subtotal'], 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $row['medio_pago'] == 'Efectivo' ? 'success' : 
                                             ($row['medio_pago'] == 'Tarjeta' ? 'primary' : 'warning'); 
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

<script>
function limpiarFiltros() {
    window.location.href = 'index.php#/caja';
}
</script>
// El script ya está incluido arriba
</script>