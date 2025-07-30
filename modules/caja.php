<?php
include('../includes/config.php');

// Obtener todas las ventas
$sql = "SELECT * FROM ventas";
$result = $conn->query($sql);
?>

<div class="container caja">
    <h2>Reporte de Caja</h2>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Total</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()) { ?>
            <tr>
                <td><?php echo $row['fecha']; ?></td>
                <td><?php echo $row['id_cliente']; ?></td>
                <td><?php echo $row['total']; ?></td>
                <td>
                    <a href="detalles_venta.php?id=<?php echo $row['id_venta']; ?>" class="btn btn-info">Ver Detalles</a>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<!-- JS específico para Caja -->
<script src="assets/js/caja.js"></script>
