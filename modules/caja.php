<?php
include('../includes/config.php');

// Obtener todas las ventas con información de cliente y mascota
$sql = "SELECT v.id_venta, v.fecha_venta as fecha, v.subtotal, v.medio_pago,
               m.nombre as nombre_mascota,
               CONCAT(c.nombre, ' ', c.apellido) as nombre_cliente,
               CASE 
                   WHEN v.tipo_item = 'producto' THEN p.nombre 
                   WHEN v.tipo_item = 'servicio' THEN s.nombre 
               END as item_nombre,
               v.tipo_item, v.cantidad, v.precio_unitario
        FROM ventas v
        LEFT JOIN mascotas m ON v.id_mascota = m.id_mascota
        LEFT JOIN clientes c ON m.id_cliente = c.id_cliente
        LEFT JOIN productos p ON v.tipo_item = 'producto' AND v.id_item = p.id_producto
        LEFT JOIN servicios s ON v.tipo_item = 'servicio' AND v.id_item = s.id_servicio
        ORDER BY v.fecha_venta DESC";
$result = $conn->query($sql);

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
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
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
                            <?php endwhile; ?>
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
        </div>
    </div>
</div>

<!-- JS para Caja -->
<script>
    $(document).ready(function() {
        // Funcionalidad adicional para caja
        // Por ejemplo, filtrado por fecha o exportación a Excel
        
        // Ejemplo: Filtrado por fecha
        $('#filtro_fecha').change(function() {
            const fecha = $(this).val();
            // Implementar lógica de filtrado
        });
        
        // Ejemplo: Exportar a Excel
        $('#btnExportarExcel').click(function() {
            // Implementar lógica de exportación
        });
    });
    </script>
</div>

<!-- JS para Caja -->
<script>
// El script ya está incluido arriba
</script>