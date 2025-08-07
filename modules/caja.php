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

$select = "v.fecha_venta as fecha, v.subtotal, v.medio_pago,
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
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center p-3 bg-white rounded shadow-sm border">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-cash-register fa-2x text-primary"></i>
                    </div>
                    <div>
                        <h2 class="mb-0 text-dark">Reporte de Caja</h2>
                        <small class="text-muted">Sistema de Control de Ventas</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="text-center">
                        <div class="text-muted small">Fecha</div>
                        <div class="fw-bold"><?php echo date('d/m/Y'); ?></div>
                    </div>
                    <div class="text-center">
                        <div class="text-muted small">Hora</div>
                        <div class="fw-bold"><?php echo date('H:i'); ?></div>
                    </div>
                    <button class="btn btn-outline-primary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Imprimir
                    </button>
                </div>
            </div>
        </div>
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
    <div class="card shadow-sm">
        <div class="card-header bg-gradient-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Registro de Ventas</h5>
                <span class="badge bg-light text-dark">Total: <?php echo count($ventas); ?> registros</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="border-0"><i class="fas fa-calendar me-1"></i>Fecha</th>
                            <th class="border-0"><i class="fas fa-user me-1"></i>Cliente / Mascota</th>
                            <th class="border-0"><i class="fas fa-box me-1"></i>Item</th>
                            <th class="border-0 text-center"><i class="fas fa-hashtag me-1"></i>Cantidad</th>
                            <th class="border-0 text-end"><i class="fas fa-money-bill me-1"></i>Subtotal</th>
                            <th class="border-0 text-center"><i class="fas fa-credit-card me-1"></i>Medio Pago</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($ventas) > 0): ?>
                            <?php foreach ($ventas as $row): ?>
                            <tr class="align-middle">
                                <td class="fw-bold text-primary"><?php echo date('d/m/Y H:i', strtotime($row['fecha'])); ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="me-2">
                                            <i class="fas fa-user-circle text-muted fa-lg"></i>
                                        </div>
                                        <div>
                                            <?php 
                                            if (!empty($row['nombre_cliente'])) {
                                                echo '<strong>' . htmlspecialchars($row['nombre_cliente']) . '</strong>';
                                                if (!empty($row['nombre_mascota'])) {
                                                    echo '<br><small class="text-muted"><i class="fas fa-paw me-1"></i>' . htmlspecialchars($row['nombre_mascota']) . '</small>';
                                                }
                                            } else {
                                                echo '<span class="text-muted"><i class="fas fa-user-slash me-1"></i>Cliente no registrado</span>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="me-2">
                                            <i class="fas fa-<?php echo $row['tipo_item'] == 'producto' ? 'box' : 'stethoscope'; ?> text-<?php echo $row['tipo_item'] == 'producto' ? 'primary' : 'success'; ?>"></i>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($row['item_nombre']); ?></strong>
                                            <br><small class="badge bg-<?php echo $row['tipo_item'] == 'producto' ? 'primary' : 'success'; ?>"><?php echo ucfirst($row['tipo_item']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info fs-6"><?php echo $row['cantidad']; ?></span>
                                </td>
                                <td class="text-end">
                                    <strong class="text-success fs-5">S/ <?php echo number_format($row['subtotal'], 2); ?></strong>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?php 
                                        echo $row['medio_pago'] == 'Efectivo' ? 'success' : 
                                             ($row['medio_pago'] == 'Tarjeta' ? 'primary' : 
                                             ($row['medio_pago'] == 'Yape' ? 'warning' : 'info')); 
                                    ?> fs-6">
                                        <i class="fas fa-<?php 
                                            echo $row['medio_pago'] == 'Efectivo' ? 'money-bill' : 
                                                 ($row['medio_pago'] == 'Tarjeta' ? 'credit-card' : 
                                                 ($row['medio_pago'] == 'Yape' ? 'mobile-alt' : 'university')); 
                                        ?> me-1"></i>
                                        <?php echo htmlspecialchars($row['medio_pago']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-cash-register fa-4x mb-3 d-block text-secondary"></i>
                                        <h4 class="text-secondary">No hay ventas registradas</h4>
                                        <p class="mb-0">No se encontraron registros de ventas en el período seleccionado</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación -->
            <div class="card-footer bg-light">
                <?php echo $pagination->generatePaginationHTML($paginationInfo, '#/caja'); ?>
            </div>
        </div>
    </div>
</div>

<script>
function limpiarFiltros() {
    window.location.href = 'index.php#/caja';
}
</script>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
}

.table-dark th {
    background-color: #343a40 !important;
    border-color: #454d55 !important;
}

.table-striped > tbody > tr:nth-of-type(odd) > td {
    background-color: rgba(0, 123, 255, 0.05);
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.1);
    transform: translateY(-1px);
    transition: all 0.2s ease;
}

.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.badge {
    font-size: 0.75rem !important;
}

@media print {
    .btn, .pagination, .card-footer {
        display: none !important;
    }
}
</style>
